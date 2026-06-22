<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Reply;
use App\Models\WebhookEvent;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class PollInboxReplies extends Command
{
    protected $signature = 'inbox:poll
                            {--limit=50 : Max messages to fetch per run}
                            {--dry-run : Show what would be imported without writing}
                            {--days=7 : Look back N days for replies}';

    protected $description = 'Poll IMAP inbox for incoming replies and create Reply records';

    private array $brandCache = [];
    private array $leadCache = [];

    public function handle(): int
    {
        $host = config('services.imap.host', 'mail.ujuziplus.com');
        $port = (int) config('services.imap.port', 993);
        $username = config('services.imap.username');
        $password = config('services.imap.password');

        if (!$username || !$password) {
            $this->error('IMAP credentials not configured. Set IMAP_HOST, IMAP_PORT, IMAP_USERNAME, IMAP_PASSWORD in .env');
            return self::FAILURE;
        }

        $this->info('=== Inbox IMAP Poll ===');
        $this->info("Connecting to {$host}:{$port}...");

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        $mailbox = '{' . $host . ':' . $port . '/ssl}INBOX';

        $imap = @imap_open($mailbox, $username, $password, OP_READONLY, 1, []);

        if (!$imap) {
            $this->error('IMAP connection failed: ' . imap_last_error());
            return self::FAILURE;
        }

        $this->info('Connected to inbox.');

        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $sinceDate = date('d-M-Y', strtotime("-{$days} days"));

        // Search for recent messages
        $messageIds = imap_search($imap, "SINCE \"{$sinceDate}\"", SE_UID);

        if (!$messageIds) {
            $this->info("No messages found since {$sinceDate}.");
            imap_close($imap);
            return self::SUCCESS;
        }

        // Take the most recent N messages
        $messageIds = array_slice($messageIds, -$limit);
        $this->info("Found " . count($messageIds) . " messages since {$sinceDate} (limited to {$limit}).");

        $stats = [
            'scanned' => 0,
            'replies_imported' => 0,
            'bounces_imported' => 0,
            'skipped_no_match' => 0,
            'skipped_already_exists' => 0,
            'skipped_outbound' => 0,
        ];

        foreach ($messageIds as $uid) {
            $stats['scanned']++;

            // Get headers
            $header = imap_headerinfo($imap, imap_msgno($imap, $uid));

            if (!$header) continue;

            $from = $header->from[0] ?? null;
            $fromEmail = $from ? ($from->mailbox . '@' . $from->host) : '';
            $fromAddress = $from ? ($from->personal ?? $from->mailbox . '@' . $from->host) : '';
            $subject = $header->subject ?? '(no subject)';
            $date = $header->date ?? date('r');
            $messageNumber = imap_msgno($imap, $uid);

            // Skip outbound messages (from our own address)
            $ourAddress = config('mail.from.address');
            if ($fromEmail === $ourAddress) {
                $stats['skipped_outbound']++;
                continue;
            }

            // Check if this is a bounce
            $fromStr = strtolower($fromEmail . ' ' . $subject . ' ' . $fromAddress);
            $isBounce = str_contains($fromStr, 'undelivered') ||
                       str_contains($fromStr, 'mail delivery failed') ||
                       str_contains($fromStr, 'mailer-daemon') ||
                       str_contains($fromStr, 'postmaster');

            // Get the body
            $body = $this->fetchBody($imap, $messageNumber);

            // Try to match to a lead by sender email
            $lead = $this->findLeadByEmail($fromEmail);
            $brand = $lead ? $this->getBrand($lead->brand_id) : null;

            if (!$lead && !$isBounce) {
                // Try to find a lead by company name in the subject or body
                $lead = $this->findLeadByContent($subject, $body);
                if ($lead) {
                    $brand = $this->getBrand($lead->brand_id);
                }
            }

            // Check if this reply already exists (by from_email + subject + date)
            $existing = Reply::where('from_email', $fromEmail)
                ->where('subject', $subject)
                ->where('direction', 'inbound')
                ->whereDate('received_at', date('Y-m-d', strtotime($date)))
                ->exists();

            if ($existing) {
                $stats['skipped_already_exists']++;
                continue;
            }

            if (!$lead && !$isBounce) {
                $stats['skipped_no_match']++;
                if ($stats['skipped_no_match'] <= 5) {
                    $this->line("  No lead match: {$fromEmail} — {$subject}");
                }
                continue;
            }

            if ($dryRun) {
                $this->line("  Would import: {$fromEmail} → " . ($lead ? $lead->company_name : 'bounce') . " — {$subject}");
                if ($isBounce) {
                    $stats['bounces_imported']++;
                } else {
                    $stats['replies_imported']++;
                }
                continue;
            }

            // Find the email message this replies to (most recent sent to this lead)
            $emailMessage = null;
            if ($lead) {
                $emailMessage = EmailMessage::where('lead_id', $lead->id)
                    ->where('status', 'sent')
                    ->latest('sent_at')
                    ->first();
            }

            // Create the Reply record
            $reply = Reply::create([
                'lead_id' => $lead?->id ?? 0,
                'brand_id' => $lead?->brand_id ?? ($brand?->id ?? 1),
                'email_message_id' => $emailMessage?->id,
                'from_email' => $fromEmail,
                'subject' => $subject,
                'body' => substr($body, 0, 10000),
                'classification' => $isBounce ? 'bounce' : 'unclassified',
                'direction' => 'inbound',
                'read' => false,
                'received_at' => date('Y-m-d H:i:s', strtotime($date)),
            ]);

            // If it's a bounce, handle suppression
            if ($isBounce && $lead && $lead->email) {
                \App\Models\Suppression::firstOrCreate(
                    ['brand_id' => $lead->brand_id, 'email' => $lead->email],
                    ['reason' => 'hard_bounce', 'notes' => 'Bounce detected via IMAP poll: ' . substr($subject, 0, 100)],
                );
            }

            // Log to activity feed
            if (!$isBounce) {
                app(ActivityLogger::class)->log([
                    'brand_id' => $lead?->brand_id,
                    'source' => 'imap.poll',
                    'event_type' => 'reply_classified',
                    'title' => "Reply received — " . ($lead?->company_name ?? $fromEmail) . " (pending classification)",
                    'body' => substr($body, 0, 500),
                    'metadata' => [
                        'lead_id' => $lead?->id,
                        'lead_name' => $lead?->company_name,
                        'from_email' => $fromEmail,
                        'subject' => $subject,
                        'classified' => false,
                    ],
                    'severity' => 'info',
                ]);
            }

            if ($isBounce) {
                $stats['bounces_imported']++;
            } else {
                $stats['replies_imported']++;
            }

            $this->line("  ✅ Imported: {$fromEmail} → " . ($lead?->company_name ?? 'bounce') . " — {$subject}");
        }

        imap_close($imap);

        $this->newLine();
        $this->info('=== Summary ===');
        $this->info("Scanned: {$stats['scanned']}");
        $this->info("Replies imported: {$stats['replies_imported']}");
        $this->info("Bounces imported: {$stats['bounces_imported']}");
        $this->info("Skipped (no lead match): {$stats['skipped_no_match']}");
        $this->info("Skipped (already exists): {$stats['skipped_already_exists']}");
        $this->info("Skipped (outbound): {$stats['skipped_outbound']}");

        return self::SUCCESS;
    }

    private function fetchBody($imap, int $msgNo): string
    {
        $structure = imap_fetchstructure($imap, $msgNo);

        $body = '';
        if (isset($structure->parts) && count($structure->parts) > 0) {
            // Multipart — try text/plain first, then text/html
            foreach ($structure->parts as $partNo => $part) {
                if (($part->subtype ?? '') === 'PLAIN') {
                    $body = imap_fetchbody($imap, $msgNo, ($partNo + 1));
                    $body = $this->decodeBody($body, $part->encoding ?? 0);
                    break;
                }
            }
            if (empty($body)) {
                foreach ($structure->parts as $partNo => $part) {
                    if (($part->subtype ?? '') === 'HTML') {
                        $body = imap_fetchbody($imap, $msgNo, ($partNo + 1));
                        $body = $this->decodeBody($body, $part->encoding ?? 0);
                        break;
                    }
                }
            }
        } else {
            // Single part
            $body = imap_body($imap, $msgNo);
            $body = $this->decodeBody($body, $structure->encoding ?? 0);
        }

        // Strip HTML tags for plain text body
        $body = trim(strip_tags($body));
        return $body ?: '(empty body)';
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => imap_base64($body),
            4 => imap_qprint($body),
            default => $body,
        };
    }

    private function findLeadByEmail(string $email): ?Lead
    {
        if (isset($this->leadCache[$email])) {
            return $this->leadCache[$email];
        }

        $lead = Lead::where('email', $email)->first();
        $this->leadCache[$email] = $lead;
        return $lead;
    }

    private function findLeadByContent(string $subject, string $body): ?Lead
    {
        // Try to find a lead by company name in the subject or body
        $leads = Lead::whereNotNull('company_name')->limit(500)->get(['id', 'company_name', 'brand_id']);

        foreach ($leads as $lead) {
            // Check if company name appears in subject or first 500 chars of body
            if (str_contains(strtolower($subject), strtolower($lead->company_name)) ||
                str_contains(strtolower(substr($body, 0, 500)), strtolower($lead->company_name))) {
                return $lead;
            }
        }

        return null;
    }

    private function getBrand(int $brandId): ?Brand
    {
        if (isset($this->brandCache[$brandId])) {
            return $this->brandCache[$brandId];
        }

        $brand = Brand::find($brandId);
        $this->brandCache[$brandId] = $brand;
        return $brand;
    }
}