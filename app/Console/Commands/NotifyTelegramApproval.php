<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Services\ActivityLogger;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class NotifyTelegramApproval extends Command
{
    protected $signature = 'emails:notify-telegram
                            {--brand= : Brand slug to notify for}
                            {--limit=10 : Max emails to include per notification}
                            {--samples=3 : Number of lead sequences to show in full detail}';

    protected $description = 'Send pending email approval requests to Telegram with detailed content preview';

    private const string NOTIFIED_CACHE_KEY = 'telegram_notified_email_ids';

    public function handle(): int
    {
        $telegram = app(TelegramService::class);

        if (! $telegram->isConfigured()) {
            $this->warn('Telegram not configured. Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID.');

            return 1;
        }

        $query = EmailMessage::query()
            ->where('approval_status', 'pending')
            ->where('status', 'draft')
            ->with(['lead:id,company_name,email,segment,subcategory,city,brand_id', 'brand:id,name,slug,color']);

        if ($brandSlug = $this->option('brand')) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        $limit = (int) $this->option('limit');
        $allEmails = $query->limit($limit)->get();

        if ($allEmails->isEmpty()) {
            $this->info('No pending emails to notify.');

            return 0;
        }

        // Filter out emails that were already notified to Telegram
        $alreadyNotified = cache(self::NOTIFIED_CACHE_KEY, []);
        $newEmails = $allEmails->reject(fn ($e) => in_array($e->id, $alreadyNotified));

        if ($newEmails->isEmpty()) {
            $this->info('All pending emails have already been notified. Skipping duplicate notification.');

            return 0;
        }

        $this->info('Notifying '.$newEmails->count().' new emails (skipping '.($allEmails->count() - $newEmails->count()).' already notified).');

        // Group by brand
        $byBrand = $newEmails->groupBy('brand.name');

        $sent = 0;

        $sentIds = [];
        $failedBrands = [];

        foreach ($byBrand as $brandName => $emails) {
            // ⬇︎ Split deer segment emails by subcategory for separate approval
            $subcategoryGroups = $emails->groupBy(fn ($e) => $e->lead?->subcategory ?? 'general');

            foreach ($subcategoryGroups as $subcategory => $group) {
                $subcategoryLabel = $subcategory === 'general' ? 'General' : ucfirst($subcategory);

                // Send the summary message first
                $summarySent = $this->sendSummaryMessage($telegram, $brandName, $group, $subcategoryLabel);

                // Send detailed sample messages for N leads
                $sampleCount = (int) $this->option('samples');
                $samplesSent = $this->sendSampleSequences($telegram, $brandName, $group, $sampleCount);

                if ($summarySent && $samplesSent) {
                    $groupIds = $group->pluck('id')->toArray();
                    $sentIds = array_merge($sentIds, $groupIds);
                    $sent += count($groupIds);
                } else {
                    $failedBrands[] = $brandName.' ('.$subcategoryLabel.')';
                    $this->error("Telegram delivery failed for {$brandName} / {$subcategoryLabel}; these emails were not marked as notified.");
                }
            }
        }

        if ($sentIds !== []) {
            // "APPROVE ALL" must only apply to IDs whose notification was delivered.
            cache()->forever('telegram_pending_batch', $sentIds);
            $this->info('Stored batch of '.count($sentIds).' email IDs for scoped approval.');
        }

        // Log to activity feed
        app(ActivityLogger::class)->log([
            'source' => 'laravel.scheduler.approval-notify',
            'event_type' => 'system',
            'title' => "{$sent} pending emails sent to Telegram for approval",
            'metadata' => [
                'total' => $allEmails->count(),
                'sent_to_telegram' => $sent,
                'brands' => array_values(array_diff($byBrand->keys()->toArray(), $failedBrands)),
                'failed_brands' => $failedBrands,
            ],
            'severity' => $failedBrands === [] ? 'info' : 'warning',
        ]);

        // Record notified IDs so we don't re-notify the same emails
        $alreadyNotified = cache(self::NOTIFIED_CACHE_KEY, []);
        cache()->forever(self::NOTIFIED_CACHE_KEY, array_unique(array_merge($alreadyNotified, $sentIds)));

        if ($failedBrands !== []) {
            $this->error("Sent {$sent} email approval requests to Telegram; delivery failed for ".count($failedBrands).' brand(s).');

            return 1;
        }

        $this->info("Sent {$sent} email approval requests to Telegram.");

        return self::SUCCESS;
    }

    /**
     * Send the summary message: total count, list of leads, batch approve/reject.
     */
    private function sendSummaryMessage(TelegramService $telegram, string $brandName, $emails, string $subcategoryLabel = ''): bool
    {
        $totalEmails = $emails->count();
        $uniqueLeads = $emails->pluck('lead.company_name')->unique()->filter()->values();
        $brandName = e($brandName);

        $subLabel = $subcategoryLabel ? " [{$subcategoryLabel}]" : '';

        $text = "📬 <b>Approval Request — {$brandName}{$subLabel}</b>\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "📊 <b>{$totalEmails}</b> emails pending approval in THIS batch\n";
        $text .= "🏢 <b>{$uniqueLeads->count()}</b> leads in this batch\n\n";
        $text .= "⚠️ <code>APPROVE ALL</code> will only approve these {$totalEmails} — not all pending system-wide.\n\n";

        // List all leads with their email counts
        $text .= "<b>Leads in this batch:</b>\n";
        $omittedLeads = 0;
        foreach ($uniqueLeads as $idx => $company) {
            $leadEmails = $emails->filter(fn ($e) => $e->lead?->company_name === $company);
            $steps = $leadEmails->pluck('sequence_step')->sort()->map(fn ($s) => "S{$s}")->implode(', ');
            $emailIds = $leadEmails->pluck('id')->map(fn ($id) => (string) $id)->implode(', ');
            $line = '  '.($idx + 1).'. <b>'.e($company)."</b> [{$steps}] IDs: {$emailIds}\n";

            // Telegram limits message text to 4096 characters after parsing.
            if (mb_strlen(strip_tags($text.$line)) > 3200) {
                $omittedLeads = $uniqueLeads->count() - $idx;
                break;
            }

            $text .= $line;
        }

        if ($omittedLeads > 0) {
            $text .= "  … and {$omittedLeads} more leads (all {$totalEmails} IDs remain in this approval batch).\n";
        }

        $text .= "\n💡 <b>How to approve:</b>\n";
        $text .= "  <code>APPROVE {id}</code> — approve one email\n";
        $text .= "  <code>REJECT {id}</code> — reject one email\n";
        $text .= "  <code>APPROVE ALL</code> — approve all pending\n";
        $text .= "  <code>REJECT ALL</code> — reject all pending\n";

        // Build inline keyboard — optional, polling also handles text commands
        $keyboard = [];
        if ($emails->count() <= 10) {
            $row = [];
            foreach ($emails as $i => $e) {
                $row[] = ['text' => "✅ {$e->id}", 'callback_data' => "approve:{$e->id}"];
                if (count($row) >= 3 || $i === $emails->count() - 1) {
                    $keyboard[] = $row;
                    $row = [];
                }
            }
            $keyboard[] = [
                ['text' => '✅ Approve All', 'callback_data' => 'approve_all'],
                ['text' => '❌ Reject All', 'callback_data' => 'reject_all'],
            ];
        }

        $replyMarkup = $keyboard === [] ? null : ['inline_keyboard' => $keyboard];

        return $telegram->sendMessage($text, 'HTML', $replyMarkup);
    }

    /**
     * Send detailed sample sequences for N leads — showing subject + body preview.
     */
    private function sendSampleSequences(TelegramService $telegram, string $brandName, $emails, int $sampleCount): bool
    {
        // Group by lead
        $byLead = $emails->groupBy('lead.company_name');

        $samples = $byLead->take($sampleCount);
        $allSent = true;

        foreach ($samples as $company => $leadEmails) {
            $lead = $leadEmails->first()?->lead;
            if (! $lead) {
                continue;
            }

            $text = '📄 <b>Sample: '.e($company)."</b>\n";
            $text .= "━━━━━━━━━━━━━━━━━━━━\n";
            $text .= 'Email: <code>'.e($lead->email)."</code>\n";
            $text .= 'Segment: '.e($lead->segment).' | City: '.e($lead->city ?? '—')."\n\n";

            foreach ($leadEmails->sortBy('sequence_step') as $email) {
                $text .= "<b>── Step {$email->sequence_step} (ID: {$email->id}) ──</b>\n";
                $text .= '<b>Subject:</b> '.e($email->subject)."\n\n";

                // Body preview (strip HTML, show first ~300 chars)
                $body = strip_tags($email->body ?? '');
                $body = trim(preg_replace('/\s+/', ' ', $body));
                $bodyPreview = mb_substr($body, 0, 400);
                if (mb_strlen($body) > 400) {
                    $bodyPreview .= '...';
                }
                $text .= e($bodyPreview)."\n\n";
            }

            $text .= "💡 Reply <code>APPROVE {$leadEmails->first()->id}</code> to approve the first email.\n";

            if (! $telegram->sendMessage($text)) {
                $allSent = false;
            }
        }

        return $allSent;
    }
}
