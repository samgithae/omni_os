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
            ->with(['lead:id,company_name,email,segment,city,brand_id', 'brand:id,name,slug,color']);

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

        $this->info('Notifying ' . $newEmails->count() . ' new emails (skipping ' . ($allEmails->count() - $newEmails->count()) . ' already notified).');

        // Group by brand
        $byBrand = $newEmails->groupBy('brand.name');

        $sent = 0;

        // Store the batch of email IDs being sent to Telegram
        // so "APPROVE ALL" only applies to THIS batch, not all pending emails
        $allIds = $newEmails->pluck('id')->toArray();
        cache()->forever('telegram_pending_batch', $allIds);
        $this->info("Stored batch of " . count($allIds) . " email IDs for scoped approval.");
        $totalInBatch = count($allIds);

        foreach ($byBrand as $brandName => $emails) {
            // Send the summary message first
            $this->sendSummaryMessage($telegram, $brandName, $emails);

            // Send detailed sample messages for N leads
            $sampleCount = (int) $this->option('samples');
            $this->sendSampleSequences($telegram, $brandName, $emails, $sampleCount);

            $sent += $emails->count();
        }

        // Log to activity feed
        app(ActivityLogger::class)->log([
            'source' => 'laravel.scheduler.approval-notify',
            'event_type' => 'system',
            'title' => "{$sent} pending emails sent to Telegram for approval",
            'metadata' => [
                'total' => $allEmails->count(),
                'sent_to_telegram' => $sent,
                'brands' => $byBrand->keys()->toArray(),
            ],
            'severity' => 'info',
        ]);

        // Record notified IDs so we don't re-notify the same emails
        $alreadyNotified = cache(self::NOTIFIED_CACHE_KEY, []);
        cache()->forever(self::NOTIFIED_CACHE_KEY, array_unique(array_merge($alreadyNotified, $allIds)));

        $this->info("Sent {$sent} email approval requests to Telegram.");

        return 0;
    }

    /**
     * Send the summary message: total count, list of leads, batch approve/reject.
     */
    private function sendSummaryMessage(TelegramService $telegram, string $brandName, $emails): void
    {
        $totalEmails = $emails->count();
        $uniqueLeads = $emails->pluck('lead.company_name')->unique()->filter()->values();

        $text = "📬 <b>Approval Request — {$brandName}</b>\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "📊 <b>{$totalEmails}</b> emails pending approval in THIS batch\n";
        $text .= "🏢 <b>{$uniqueLeads->count()}</b> leads in this batch\n\n";
        $text .= "⚠️ <code>APPROVE ALL</code> will only approve these {$totalEmails} — not all pending system-wide.\n\n";

        // List all leads with their email counts
        $text .= "<b>Leads in this batch:</b>\n";
        foreach ($uniqueLeads as $idx => $company) {
            $leadEmails = $emails->filter(fn ($e) => $e->lead?->company_name === $company);
            $steps = $leadEmails->pluck('sequence_step')->sort()->map(fn ($s) => "S{$s}")->implode(', ');
            $emailIds = $leadEmails->pluck('id')->map(fn ($id) => (string) $id)->implode(', ');
            $text .= "  " . ($idx + 1) . ". <b>{$company}</b> [{$steps}] IDs: {$emailIds}\n";
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

        $payload = [
            'chat_id' => config('services.telegram.chat_id'),
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ];

        \Illuminate\Support\Facades\Http::post(
            "https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/sendMessage",
            $payload
        );
    }

    /**
     * Send detailed sample sequences for N leads — showing subject + body preview.
     */
    private function sendSampleSequences(TelegramService $telegram, string $brandName, $emails, int $sampleCount): void
    {
        // Group by lead
        $byLead = $emails->groupBy('lead.company_name');

        $samples = $byLead->take($sampleCount);

        foreach ($samples as $company => $leadEmails) {
            $lead = $leadEmails->first()?->lead;
            if (!$lead) continue;

            $text = "📄 <b>Sample: {$company}</b>\n";
            $text .= "━━━━━━━━━━━━━━━━━━━━\n";
            $text .= "Email: <code>{$lead->email}</code>\n";
            $text .= "Segment: {$lead->segment} | City: " . ($lead->city ?? '—') . "\n\n";

            foreach ($leadEmails->sortBy('sequence_step') as $email) {
                $text .= "<b>── Step {$email->sequence_step} (ID: {$email->id}) ──</b>\n";
                $text .= "<b>Subject:</b> {$email->subject}\n\n";

                // Body preview (strip HTML, show first ~300 chars)
                $body = strip_tags($email->body ?? '');
                $body = trim(preg_replace('/\s+/', ' ', $body));
                $bodyPreview = mb_substr($body, 0, 400);
                if (mb_strlen($body) > 400) {
                    $bodyPreview .= '...';
                }
                $text .= "{$bodyPreview}\n\n";
            }

            $text .= "💡 Reply <code>APPROVE {$leadEmails->first()->id}</code> to approve the first email.\n";

            $payload = [
                'chat_id' => config('services.telegram.chat_id'),
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            \Illuminate\Support\Facades\Http::post(
                "https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/sendMessage",
                $payload
            );
        }
    }
}