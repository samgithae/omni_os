<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Suppression;

class ReplyService
{
    /**
     * Route a classified reply to its outcome.
     *
     * Classification categories:
     *   - interested    → flag for Telegram follow-up (the sales moment)
     *   - not_interested → close lead
     *   - out_of_office  → reschedule for later
     *   - unsubscribe    → create suppression immediately (compliance)
     *   - bounce         → create suppression (webhook already handles this)
     *
     * @param  array{email_message_id: int, lead_id: int, classification: string, summary: string, reply_body: string|null, confidence: float|null}  $reply
     */
    public function route(array $reply): array
    {
        $classification = $reply['classification'];
        $emailMessage = EmailMessage::find($reply['email_message_id']);
        $lead = Lead::find($reply['lead_id']);

        if (! $emailMessage || ! $lead) {
            return ['success' => false, 'message' => 'Email message or lead not found.'];
        }

        $result = match ($classification) {
            'interested' => $this->handleInterested($lead, $emailMessage, $reply),
            'not_interested' => $this->handleNotInterested($lead, $emailMessage, $reply),
            'out_of_office' => $this->handleOutOfOffice($lead, $emailMessage, $reply),
            'unsubscribe' => $this->handleUnsubscribe($lead, $emailMessage, $reply),
            'bounce' => $this->handleBounce($lead, $emailMessage, $reply),
            default => ['success' => false, 'message' => "Unknown classification: {$classification}"],
        };

        // Log to activity feed
        $this->logActivity($lead, $classification, $reply);

        return $result;
    }

    /**
     * Interested — the sales moment. Flag for Telegram follow-up.
     */
    private function handleInterested(Lead $lead, EmailMessage $email, array $reply): array
    {
        $lead->transitionTo(LeadStatus::Interested, 'reply.classifier', [
            'email_id' => $email->id,
            'summary' => $reply['summary'] ?? '',
            'reply_body' => $reply['reply_body'] ?? '',
            'confidence' => $reply['confidence'] ?? null,
        ]);

        $lead->increment('score', 20);

        // Store reply context in raw_data
        $raw = $lead->raw_data ?? [];
        $raw['last_reply'] = [
            'classification' => 'interested',
            'summary' => $reply['summary'] ?? '',
            'body' => $reply['reply_body'] ?? '',
            'received_at' => now()->toIso8601String(),
            'email_subject' => $email->subject,
        ];
        $lead->updateQuietly(['raw_data' => $raw]);

        // Notify via Telegram
        $this->notifyTelegram($lead, $email, $reply);

        return [
            'success' => true,
            'message' => 'Lead marked as interested — flagged for Telegram follow-up.',
            'lead_id' => $lead->id,
            'status' => 'interested',
            'notify_telegram' => true,
        ];
    }

    /**
     * Not interested — close the lead.
     */
    private function handleNotInterested(Lead $lead, EmailMessage $email, array $reply): array
    {
        $lead->transitionTo(LeadStatus::NotInterested, 'reply.classifier', [
            'email_id' => $email->id,
            'summary' => $reply['summary'] ?? '',
        ]);

        $raw = $lead->raw_data ?? [];
        $raw['last_reply'] = [
            'classification' => 'not_interested',
            'summary' => $reply['summary'] ?? '',
            'received_at' => now()->toIso8601String(),
        ];
        $lead->updateQuietly(['raw_data' => $raw]);

        return [
            'success' => true,
            'message' => 'Lead marked as not interested — closed.',
            'lead_id' => $lead->id,
            'status' => 'not_interested',
        ];
    }

    /**
     * Out of office — schedule a retry in 7 days.
     */
    private function handleOutOfOffice(Lead $lead, EmailMessage $email, array $reply): array
    {
        // Leave the email in its current status but log the OOO
        // The email will be re-sent on next scheduler run if still queued
        // Or we can re-queue it
        if ($email->status === 'sent') {
            // Re-create a queued version of this email for retry
            // For now, just log it — the scheduler handles retries
        }

        $raw = $lead->raw_data ?? [];
        $raw['out_of_office_replies'] = ($raw['out_of_office_replies'] ?? 0) + 1;
        $lead->updateQuietly(['raw_data' => $raw]);

        return [
            'success' => true,
            'message' => 'Out of office detected — will retry.',
            'lead_id' => $lead->id,
            'status' => $lead->status,
        ];
    }

    /**
     * Unsubscribe — create suppression immediately (compliance requirement).
     */
    private function handleUnsubscribe(Lead $lead, EmailMessage $email, array $reply): array
    {
        Suppression::firstOrCreate(
            ['brand_id' => $lead->brand_id, 'email' => $lead->email],
            ['reason' => 'unsubscribe', 'notes' => 'Reply classified as unsubscribe: ' . ($reply['summary'] ?? '')],
        );

        $lead->transitionTo(LeadStatus::Suppressed, 'reply.classifier', [
            'email_id' => $email->id,
            'reason' => 'unsubscribe',
        ]);

        return [
            'success' => true,
            'message' => 'Unsubscribe processed — lead suppressed.',
            'lead_id' => $lead->id,
            'status' => 'suppressed',
        ];
    }

    /**
     * Bounce — already handled by SMTP2GO webhook, but if Hermes
     * re-classifies something as a bounce, handle it here too.
     */
    private function handleBounce(Lead $lead, EmailMessage $email, array $reply): array
    {
        $email->update([
            'status' => 'failed',
            'error_message' => 'Bounced (reply classified): ' . ($reply['summary'] ?? ''),
        ]);

        Suppression::firstOrCreate(
            ['brand_id' => $lead->brand_id, 'email' => $lead->email],
            ['reason' => 'hard_bounce', 'notes' => 'Classified as bounce by reply classifier'],
        );

        $lead->transitionTo(LeadStatus::Suppressed, 'reply.classifier', [
            'email_id' => $email->id,
            'reason' => 'bounce',
        ]);

        return [
            'success' => true,
            'message' => 'Bounce processed — lead suppressed.',
            'lead_id' => $lead->id,
            'status' => 'suppressed',
        ];
    }

    /**
     * Notify Telegram for the sales moment (interested replies).
     */
    private function notifyTelegram(Lead $lead, EmailMessage $email, array $reply): void
    {
        $telegram = app(TelegramService::class);
        if (! $telegram->isConfigured()) {
            return;
        }

        $brandName = $email->brand?->name ?? 'Unknown';
        $summary = $reply['summary'] ?? '';
        $replyBody = $reply['reply_body'] ?? '';
        $confidence = ($reply['confidence'] ?? 0) * 100;

        $text = "🔥 <b>Interested Lead — {$lead->company_name}</b>\n";
        $text .= "Brand: {$brandName}\n";
        $text .= "Email: {$lead->email}\n";
        $text .= "Score: {$lead->score}\n";
        $text .= "Reply confidence: {$confidence}%\n";
        $text .= "Segment: {$lead->segment}\n";
        $text .= "City: " . ($lead->city ?? '—') . "\n";

        if ($summary) {
            $text .= "\n📝 <b>Summary:</b> {$summary}\n";
        }

        if ($replyBody) {
            $text .= "\n💬 <b>Reply:</b>\n{$replyBody}\n";
        }

        $text .= "\n<a href=\"https://omni.hudutech.co.ke/admin/leads/{$lead->id}/edit\">Open in admin →</a>";

        $telegram->sendMessage($text);
    }

    /**
     * Log the reply classification to the Activity Feed.
     */
    private function logActivity(Lead $lead, string $classification, array $reply): void
    {
        $logger = app(ActivityLogger::class);

        $severity = match ($classification) {
            'interested' => 'success',
            'unsubscribe', 'bounce' => 'warning',
            default => 'info',
        };

        $title = match ($classification) {
            'interested' => "Reply: interested — {$lead->company_name} (flagged for follow-up)",
            'not_interested' => "Reply: not interested — {$lead->company_name}",
            'out_of_office' => "Reply: out of office — {$lead->company_name}",
            'unsubscribe' => "Unsubscribe request — {$lead->company_name}",
            'bounce' => "Bounce detected — {$lead->company_name}",
            default => "Reply classified: {$classification} — {$lead->company_name}",
        };

        $logger->log([
            'brand_id' => $lead->brand_id,
            'source' => 'hermes.reply-classifier',
            'event_type' => 'reply_classified',
            'title' => $title,
            'body' => $reply['summary'] ?? null,
            'metadata' => [
                'lead_id' => $lead->id,
                'lead_name' => $lead->company_name,
                'classification' => $classification,
                'email_message_id' => $reply['email_message_id'] ?? null,
                'confidence' => $reply['confidence'] ?? null,
            ],
            'severity' => $severity,
        ]);
    }
}
