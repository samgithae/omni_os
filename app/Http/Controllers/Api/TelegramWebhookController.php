<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailMessage;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TelegramWebhookController extends Controller
{
    /**
     * Receive incoming Telegram messages (replies to approval requests).
     *
     * Expected formats:
     *   "APPROVE 123"       — approve email #123
     *   "REJECT 123"        — reject email #123
     *   "APPROVE ALL"       — approve all pending emails for the brand
     *   "REJECT ALL"        — reject all pending emails for the brand
     *
     * Also handles inline keyboard callback_data:
     *   "approve:123"       — approve email #123
     *   "reject:123"        — reject email #123
     *   "approve_all"       — approve all pending
     *   "reject_all"        — reject all pending
     */
    public function handle(Request $request)
    {
        $webhookSecret = config('services.telegram.webhook_secret');
        if ($webhookSecret && $request->input('secret') !== $webhookSecret) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // Parse the message — could be a text reply or inline callback
        $message = $request->input('message.text');
        $callbackData = $request->input('callback_query.data');

        $command = $callbackData ?? $message;

        if (! $command) {
            return response()->json(['message' => 'No command.'], 422);
        }

        $result = $this->processCommand($command);

        return response()->json($result);
    }

    private function processCommand(string $command): array
    {
        $command = strtoupper(trim($command));

        // Single approve/reject with ID
        if (preg_match('/^(APPROVE|REJECT)\s+(\d+)$/', $command, $matches)) {
            $action = strtolower($matches[1]);
            $emailId = (int) $matches[2];

            $email = EmailMessage::find($emailId);
            if (! $email) {
                return ['success' => false, 'message' => "Email #{$emailId} not found."];
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

            $this->logActivity($email, $action);

            return [
                'success' => true,
                'message' => "Email #{$emailId} {$action}d.",
                'email_id' => $emailId,
                'action' => $action,
            ];
        }

        // Bulk actions
        if (in_array($command, ['APPROVE ALL', 'APPROVE_ALL', 'REJECT ALL', 'REJECT_ALL'], true)) {
            $isApprove = str_starts_with($command, 'APPROVE');
            $newStatus = $isApprove ? 'approved' : 'rejected';

            // Only approve/reject the batch that was most recently sent to Telegram
            $batchIds = Cache::get('telegram_pending_batch', []);
            if (empty($batchIds)) {
                $query = EmailMessage::where('approval_status', 'pending')
                    ->where('status', 'draft');
            } else {
                $query = EmailMessage::whereIn('id', $batchIds)
                    ->where('approval_status', 'pending')
                    ->where('status', 'draft');
            }

            $count = $query->update([
                'approval_status' => $newStatus,
                'status' => $isApprove ? 'queued' : 'draft',
                ($isApprove ? 'approved_at' : 'rejected_at') => now(),
            ]);

            // Clear the batch cache
            Cache::forget('telegram_pending_batch');

            $this->logActivity(null, $isApprove ? 'approve_all' : 'reject_all', $count);

            return [
                'success' => true,
                'message' => "{$count} emails {$newStatus}.",
                'count' => $count,
                'action' => $isApprove ? 'approve_all' : 'reject_all',
            ];
        }

        return ['success' => false, 'message' => "Unknown command: {$command}"];
    }

    private function logActivity(?EmailMessage $email, string $action, ?int $count = null): void
    {
        $logger = app(ActivityLogger::class);

        $logger->log([
            'brand_id' => $email?->brand_id,
            'source' => 'telegram.approval-gate',
            'event_type' => match ($action) {
                'approve', 'approve_all' => 'email_approved',
                'reject', 'reject_all' => 'email_rejected',
                default => 'system',
            },
            'title' => match (true) {
                $action === 'approve' => "Email #{$email->id} approved via Telegram",
                $action === 'reject' => "Email #{$email->id} rejected via Telegram",
                $action === 'approve_all' => "{$count} emails approved via Telegram (bulk)",
                $action === 'reject_all' => "{$count} emails rejected via Telegram (bulk)",
                default => "Telegram action: {$action}",
            },
            'metadata' => [
                'email_id' => $email?->id,
                'action' => $action,
                'count' => $count,
                'source' => 'telegram',
            ],
            'severity' => in_array($action, ['approve', 'approve_all']) ? 'success' : 'warning',
        ]);
    }
}
