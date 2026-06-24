<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendEmailBatch extends Command
{
    protected $signature = 'emails:send-batch
                            {--brand= : Brand slug to send for}
                            {--limit=20 : Max emails to send per run}
                            {--force : Skip MX check}';

    protected $description = 'Send approved/queued emails via SMTP2GO with safe-send discipline';

    private const int MAX_PER_DOMAIN = 5;        // domain warming limit
    private const string MX_CACHE_KEY = 'omni_mx_valid_';

    public function handle(): int
    {
        $apiKey = config('services.smtp2go.api_key');
        $apiEndpoint = config('services.smtp2go.api_endpoint', 'https://api.smtp2go.com/v3');
        $force = $this->option('force');

        if (! $apiKey) {
            $this->error('SMTP2GO API key not configured.');
            return 1;
        }

        $query = EmailMessage::query()
            ->where('approval_status', 'approved')
            ->where('status', 'queued')
            ->with(['lead:id,company_name,email,contact_name', 'brand:id,name,slug']);

        if ($brandSlug = $this->option('brand')) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        $limit = (int) $this->option('limit');
        $emails = $query->limit($limit)->get();

        if ($emails->isEmpty()) {
            $this->info('No queued emails to send.');
            return 0;
        }

        $this->info("Found {$emails->count()} queued emails.");

        $sent = 0;
        $failed = 0;
        $domainCount = []; // track per-domain send volume
        $results = [];

        foreach ($emails as $email) {
            $lead = $email->lead;
            $brand = $email->brand;

            if (! $lead || ! $lead->email) {
                $email->update(['status' => 'failed', 'error_message' => 'No lead email address.']);
                $failed++;
                continue;
            }

            $domain = substr(strrchr($lead->email, '@'), 1);

            // MX check (once per domain, cached)
            if (! $force && ! $this->domainHasMx($domain)) {
                $this->warn("  Skipping {$lead->email}: domain {$domain} has no MX record");
                $email->update(['status' => 'failed', 'error_message' => "Domain {$domain} has no MX record."]);
                $failed++;
                continue;
            }

            // Domain warming — limit sends per domain
            $domainCount[$domain] = ($domainCount[$domain] ?? 0) + 1;
            if ($domainCount[$domain] > self::MAX_PER_DOMAIN) {
                $this->warn("  Skipping {$lead->email}: domain warming limit ({$domain})");
                continue; // Leave as queued — try next run
            }

            $this->line("  Sending to {$lead->email}: {$email->subject}");

            try {
                // Random sender email from the brand's pool (rotate to avoid spam flags)
                $senderEmail = $brand?->randomSenderEmail() ?? config('mail.from.address');
                $senderName = $brand?->sender_name ?? config('mail.from.name', 'Omni OS');

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Smtp2go-Api-Key' => $apiKey,
                ])->post(rtrim($apiEndpoint, '/').'/email/send', [
                    'to' => [$lead->email],
                    'sender' => $senderEmail,
                    'sender_name' => $senderName,
                    'subject' => $email->subject,
                    'html_body' => $email->body, // Already HTML from import
                    'text_body' => strip_tags((string) $email->body),
                    'custom_headers' => [
                        ['header' => 'X-Omni-OS-Email-ID', 'value' => (string) $email->id],
                    ],
                ]);

                if ($response->successful()) {
                    $email->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    // Transition lead to emailed
                    try {
                        $lead->transitionTo(\App\Enums\LeadStatus::Emailed, 'cli.emails.send', [
                            'email_id' => $email->id,
                            'sequence_step' => $email->sequence_step,
                        ]);
                    } catch (\Throwable $transitionError) {
                        $this->warn("    Lead transition skipped: {$transitionError->getMessage()}");
                    }

                    $this->info("    ✅ Sent (ID: {$email->id})");
                    $sent++;
                } else {
                    $email->update([
                        'status' => 'failed',
                        'error_message' => substr($response->body(), 0, 500),
                    ]);
                    $this->error("    ❌ Failed: {$response->status()} {$response->body()}");
                    $failed++;
                }
            } catch (\Throwable $e) {
                $email->update([
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
                $this->error("    ❌ Exception: {$e->getMessage()}");
                $failed++;
            }

            // Randomized delay between sends (500ms – 3s)
            if ($sent + $failed < $emails->count()) {
                $delay = random_int(500000, 3000000); // microseconds
                usleep($delay);
            }
        }

        // Log to activity feed
        $logger = app(ActivityLogger::class);
        $brandNames = $emails->pluck('brand.name')->unique()->filter()->values()->toArray();
        $logger->log([
            'source' => 'laravel.scheduler.email-sender',
            'event_type' => 'email_sent_batch',
            'title' => "{$sent} emails sent" . ($failed ? ", {$failed} failed" : '') . ($brandNames ? ' — ' . implode(', ', $brandNames) : ''),
            'metadata' => [
                'total' => $emails->count(),
                'sent' => $sent,
                'failed' => $failed,
                'brands' => $brandNames,
                'domains' => array_keys($domainCount),
            ],
            'severity' => $failed > 0 ? 'warning' : 'success',
        ]);

        $this->newLine();
        $this->info("Done: {$sent} sent, {$failed} failed.");

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Quick DNS MX check — checks if the domain accepts email.
     * Caches valid results for 24h to avoid repeated DNS queries per domain.
     */
    private function domainHasMx(string $domain): bool
    {
        // Skip check for common major providers (they always have MX)
        $knownGood = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com',
                       'aol.com', 'protonmail.com', 'mail.com', 'zoho.com', 'yandex.com',
                       'live.com', 'msn.com', 'ymail.com'];

        if (in_array(strtolower($domain), $knownGood, true)) {
            return true;
        }

        $cacheKey = self::MX_CACHE_KEY . str_replace('.', '_', $domain);
        $cached = cache()->get($cacheKey);

        if ($cached !== null) {
            return (bool) $cached;
        }

        $hasMx = checkdnsrr($domain, 'MX');
        cache()->put($cacheKey, $hasMx, now()->addHours(24));

        return $hasMx;
    }
}
