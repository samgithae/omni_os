<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PollTelegramApprovals extends Command
{
    protected $signature = 'telegram:poll-approvals';

    protected $description = 'Poll Telegram for approval replies and process them';

    private ?string $botToken;

    private int $lastUpdateId = 0;

    private const CACHE_KEY = 'telegram_last_update_id';

    private const NOTIFIED_CACHE_KEY = 'telegram_notified_email_ids';

    public function handle(): int
    {
        $this->botToken = config('services.telegram.bot_token');

        if (! $this->botToken) {
            $this->warn('Telegram bot token not configured.');

            return 1;
        }

        $this->loadLastUpdateId();
        $updates = $this->getUpdates();

        if ($updates === false) {
            // Network error — don't advance offset, try again next minute
            $this->warn('Telegram API unreachable. Will retry on next poll.');

            return 1;
        }

        if (empty($updates)) {
            return 0;
        }

        $processed = 0;

        foreach ($updates as $update) {
            $updateId = $update['update_id'] ?? 0;
            if ($updateId > $this->lastUpdateId) {
                $this->lastUpdateId = $updateId;
            }

            $message = $update['message']['text'] ?? $update['callback_query']['data'] ?? null;
            $callbackQuery = $update['callback_query'] ?? null;

            if (! $message) {
                continue;
            }

            $result = $this->processCommand($message, $callbackQuery);
            if ($result['success']) {
                $processed++;
                $this->info("Processed: {$result['message']}");
            }

            // Answer callback query if applicable
            if ($callbackQuery && isset($update['callback_query']['id'])) {
                $this->answerCallbackQuery($update['callback_query']['id'], $result['message'] ?? 'Done');
            }
        }

        $this->saveLastUpdateId();

        if ($processed > 0) {
            $this->info("Processed {$processed} approval commands.");
        }

        return 0;
    }

    private function getUpdates(): array|false
    {
        try {
            $response = Http::timeout(15)->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])->get(
                "https://api.telegram.org/bot{$this->botToken}/getUpdates",
                [
                    'offset' => $this->lastUpdateId + 1,
                    'timeout' => 30,
                    'allowed_updates' => ['message', 'callback_query'],
                ]
            );

            if ($response->successful()) {
                $data = $response->json();

                return $data['result'] ?? [];
            }
        } catch (\Throwable $e) {
            $this->error("Telegram API error: {$e->getMessage()}");
        }

        return false;
    }

    private function processCommand(string $command, ?array $callbackQuery = null): array
    {
        $command = strtoupper(trim($command));

        // Parse inline callback data: "approve:123", "reject:123", "approve_all", "reject_all"
        if (preg_match('/^(approve|reject):(\d+)$/', $command, $matches)) {
            return $this->handleSingleAction($matches[1], (int) $matches[2]);
        }

        if ($command === 'APPROVE_ALL' || $command === 'approve_all') {
            return $this->handleBulkAction('approve');
        }

        if ($command === 'REJECT_ALL' || $command === 'reject_all') {
            return $this->handleBulkAction('reject');
        }

        if (preg_match('/^(APPROVE|REJECT)\s+(\d+)$/', $command, $matches)) {
            return $this->handleSingleAction(strtolower($matches[1]), (int) $matches[2]);
        }

        return ['success' => false, 'message' => "Unknown command: {$command}"];
    }

    private function handleSingleAction(string $action, int $emailId): array
    {
        $email = EmailMessage::find($emailId);
        if (! $email) {
            return ['success' => false, 'message' => "Email #{$emailId} not found."];
        }

        if ($email->approval_status !== 'pending') {
            return ['success' => false, 'message' => "Email #{$emailId} is not pending approval (current: {$email->approval_status})."];
        }

        if ($action === 'approve') {
            $email->update([
                'approval_status' => 'approved',
                'status' => 'queued',
                'approved_at' => now(),
            ]);
        } else {
            $email->update([
                'approval_status' => 'rejected',
                'rejected_at' => now(),
            ]);
        }

        // Remove from notified cache so it won't be re-notified
        $this->removeFromNotifiedCache($emailId);

        $this->logActivity($email, $action);

        return [
            'success' => true,
            'message' => "Email #{$emailId} {$action}d.",
            'email_id' => $emailId,
            'action' => $action,
        ];
    }

    private function handleBulkAction(string $action): array
    {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        // Only approve/reject the batch that was most recently sent to Telegram
        $batchIds = cache('telegram_pending_batch', []);
        if (empty($batchIds)) {
            // Fallback: no batch stored — approve all pending (legacy behavior with warning)
            $query = EmailMessage::where('approval_status', 'pending')->where('status', 'draft');
        } else {
            $query = EmailMessage::whereIn('id', $batchIds)
                ->where('approval_status', 'pending')
                ->where('status', 'draft');
        }

        $count = $query->update([
            'approval_status' => $newStatus,
            'status' => $action === 'approve' ? 'queued' : 'draft',
            ($action === 'approve' ? 'approved_at' : 'rejected_at') => now(),
        ]);

        // Clear the batch cache so it can't be re-applied
        cache()->forget('telegram_pending_batch');

        // Clear the notified cache so these emails won't be re-notified
        cache()->forget(self::NOTIFIED_CACHE_KEY);

        if ($count > 0) {
            $logger = app(ActivityLogger::class);
            $logger->log([
                'source' => 'telegram.approval-gate',
                'event_type' => $action === 'approve' ? 'email_approved' : 'email_rejected',
                'title' => "{$count} emails {$newStatus} via Telegram (bulk)",
                'metadata' => ['count' => $count, 'method' => 'telegram'],
                'severity' => $action === 'approve' ? 'success' : 'info',
            ]);
        }

        return [
            'success' => true,
            'message' => "{$count} emails {$newStatus}.",
            'count' => $count,
            'action' => $action,
        ];
    }

    private function logActivity(EmailMessage $email, string $action): void
    {
        $logger = app(ActivityLogger::class);
        $logger->log([
            'brand_id' => $email->brand_id,
            'source' => 'telegram.approval-gate',
            'event_type' => $action === 'approve' ? 'email_approved' : 'email_rejected',
            'title' => $action === 'approve'
                ? "Email #{$email->id} approved via Telegram"
                : "Email #{$email->id} rejected via Telegram",
            'metadata' => [
                'email_id' => $email->id,
                'lead_id' => $email->lead_id,
                'sequence_step' => $email->sequence_step,
                'subject' => $email->subject,
                'method' => 'telegram',
            ],
            'severity' => $action === 'approve' ? 'success' : 'info',
        ]);
    }

    private function removeFromNotifiedCache(int $emailId): void
    {
        $notified = cache(self::NOTIFIED_CACHE_KEY, []);
        $filtered = array_values(array_filter($notified, fn ($id) => (int) $id !== $emailId));
        if (count($filtered) !== count($notified)) {
            cache()->forever(self::NOTIFIED_CACHE_KEY, $filtered);
        }
    }

    private function answerCallbackQuery(string $callbackId, string $text): void
    {
        try {
            Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->timeout(5)->post(
                "https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery",
                [
                    'callback_query_id' => $callbackId,
                    'text' => $text,
                    'show_alert' => false,
                ]
            );
        } catch (\Throwable $e) {
            // Silently fail — not critical
        }
    }

    private function loadLastUpdateId(): void
    {
        $cached = cache(self::CACHE_KEY);
        if ($cached !== null) {
            $this->lastUpdateId = (int) $cached;
        }
    }

    private function saveLastUpdateId(): void
    {
        cache()->forever(self::CACHE_KEY, (string) $this->lastUpdateId);
    }
}
