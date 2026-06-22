<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailMessage;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Handle SMTP2GO webhook callbacks for open/click/bounce tracking.
     *
     * SMTP2GO sends POST with JSON body containing events array.
     * Each event has: event_type, x_smtp2go_id, custom_headers, etc.
     */
    public function smtp2go(Request $request)
    {
        // Verify API key if configured
        $webhookKey = config('services.smtp2go.webhook_key');
        if ($webhookKey) {
            $signature = $request->header('X-SMTP2GO-Signature')
                ?? $request->header('X-Smtp2go-Signature');

            // Simple key check if signature validation not available
            if ($request->input('api_key') !== $webhookKey) {
                return response()->json(['message' => 'Unauthorized.'], 401);
            }
        }

        $events = $request->input('events', []);

        if (empty($events)) {
            // SMTP2GO may send a single event object
            $single = $request->input('event_type');
            if ($single) {
                $events = [$request->all()];
            }
        }

        $processed = 0;

        foreach ($events as $event) {
            $eventType = $event['event_type'] ?? null;
            $customHeaders = $event['custom_headers'] ?? [];

            // Find our email ID from custom headers
            $emailId = null;
            foreach ($customHeaders as $header) {
                $hdr = $header['header'] ?? $header['name'] ?? '';
                $val = $header['value'] ?? $header['val'] ?? '';
                if (strtolower($hdr) === 'x-omni-os-email-id') {
                    $emailId = (int) $val;
                    break;
                }
            }

            if (! $emailId) {
                continue;
            }

            $email = EmailMessage::find($emailId);
            if (! $email) {
                continue;
            }

            match ($eventType) {
                'open', 'opened' => $email->update(['opened_at' => now()]),
                'click', 'clicked' => $email->update(['clicked_at' => now()]),
                'bounce', 'bounced', 'hard_bounce' => $this->handleBounce($email, $event),
                'complaint', 'spam' => $this->handleComplaint($email, $event),
                'unsubscribe' => $this->handleUnsubscribe($email, $event),
                'reply' => $this->handleReply($email, $event),
                default => null,
            };

            $processed++;
        }

        return response()->json([
            'message' => 'Webhook processed.',
            'events_received' => count($events),
            'events_processed' => $processed,
        ]);
    }

    protected function handleBounce(EmailMessage $email, array $event): void
    {
        $reason = $event['reason'] ?? 'unknown';
        $recipient = $event['recipient'] ?? $email->lead?->email;

        $email->update([
            'status' => 'failed',
            'error_message' => 'Bounced: ' . $reason,
        ]);

        // Create suppression record if we know the email
        if ($recipient && $email->lead) {
            \App\Models\Suppression::firstOrCreate(
                ['brand_id' => $email->brand_id, 'email' => $recipient],
                ['reason' => 'hard_bounce', 'notes' => 'Bounced via SMTP2GO webhook: ' . $reason],
            );
        }
    }

    protected function handleComplaint(EmailMessage $email, array $event): void
    {
        $recipient = $event['recipient'] ?? $email->lead?->email;

        $email->update([
            'status' => 'failed',
            'error_message' => 'Spam complaint',
        ]);

        if ($recipient && $email->lead) {
            \App\Models\Suppression::firstOrCreate(
                ['brand_id' => $email->brand_id, 'email' => $recipient],
                ['reason' => 'spam_complaint', 'notes' => 'Spam complaint via SMTP2GO'],
            );
        }
    }

    protected function handleUnsubscribe(EmailMessage $email, array $event): void
    {
        $recipient = $event['recipient'] ?? $email->lead?->email;

        $email->update([
            'status' => 'failed',
            'error_message' => 'Unsubscribed',
        ]);

        if ($recipient && $email->lead) {
            \App\Models\Suppression::firstOrCreate(
                ['brand_id' => $email->brand_id, 'email' => $recipient],
                ['reason' => 'unsubscribe', 'notes' => 'Unsubscribed via SMTP2GO list-unsubscribe'],
            );
        }
    }

    /**
     * Handle a reply event from SMTP2GO.
     * Logs the raw reply and leaves classification to Hermes.
     * Hermes will call POST /api/v1/replies with the classification.
     */
    protected function handleReply(EmailMessage $email, array $event): void
    {
        $lead = $email->lead;
        if (! $lead) {
            return;
        }

        $replyText = $event['plain_text_body']
            ?? $event['text']
            ?? $event['body']
            ?? '(no content)';

        $replySubject = $event['subject'] ?? '(no subject)';

        // Store raw reply on the lead for Hermes to classify
        $raw = $lead->raw_data ?? [];
        $raw['incoming_replies'] = $raw['incoming_replies'] ?? [];
        $raw['incoming_replies'][] = [
            'email_message_id' => $email->id,
            'subject' => $replySubject,
            'body' => substr($replyText, 0, 5000),
            'received_at' => now()->toIso8601String(),
            'classified' => false,
        ];
        $lead->updateQuietly(['raw_data' => $raw]);

        // Log to activity feed as unclassified reply
        $logger = app(\App\Services\ActivityLogger::class);
        $logger->log([
            'brand_id' => $email->brand_id,
            'source' => 'smtp2go.webhook.reply',
            'event_type' => 'reply_classified',
            'title' => "Reply received — {$lead->company_name} (pending classification)",
            'body' => substr($replyText, 0, 500),
            'metadata' => [
                'lead_id' => $lead->id,
                'lead_name' => $lead->company_name,
                'email_message_id' => $email->id,
                'subject' => $replySubject,
                'classified' => false,
            ],
            'severity' => 'info',
        ]);
    }
}
