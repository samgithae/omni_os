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
                            {--limit=10 : Max emails to include per notification}';

    protected $description = 'Send pending email approval requests to Telegram';

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
            ->with(['lead:id,company_name,email', 'brand:id,name,slug']);

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

        // Group by brand for separate messages
        $byBrand = $allEmails->groupBy('brand.name');

        $sent = 0;

        foreach ($byBrand as $brandName => $emails) {
            $emailData = [
                'brand_name' => $brandName,
                'total' => $emails->count(),
                'emails' => $emails->map(fn($e) => [
                    'id' => $e->id,
                    'sequence_step' => $e->sequence_step,
                    'subject' => $e->subject,
                    'company_name' => $e->lead?->company_name ?? 'Unknown',
                ])->toArray(),
            ];

            $ok = $telegram->sendApprovalRequest($emailData);
            if ($ok) {
                $sent += $emails->count();
            }
        }

        // Log to activity feed
        $logger = app(ActivityLogger::class);
        $logger->log([
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

        $this->info("Sent {$sent} email approval requests to Telegram.");

        return 0;
    }
}
