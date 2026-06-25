<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Reply;
use App\Models\Suppression;
use App\Models\WebhookEvent;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle SMTP2GO webhook callbacks for open/click/bounce/reply tracking.
     *
     * SMTP2GO sends POST with JSON body containing events array or a single event.
     * Every event is persisted to webhook_events BEFORE processing — audit trail first.
     * Always returns 200 so SMTP2GO doesn't retry-storm.
     */
    public function smtp2go(Request $request)
    {
        // 1. Validate api_key (if configured)
        $webhookKey = config('services.smtp2go.webhook_key');
        if ($webhookKey) {
            if ($request->input('api_key') !== $webhookKey) {
                return response()->json(['message' => 'Unauthorized.'], 401);
            }
        }

        // 2. Normalize events into an array
        $events = $request->input('events', []);
        if (empty($events)) {
            $single = $request->input('event_type') ?? $request->input('event');
            if ($single) {
                $events = [$request->all()];
            }
        }

        // 3. Persist + process each event
        $processedCount = 0;
        foreach ($events as $event) {
            $eventType = $event['event_type'] ?? $event['event'] ?? null;
            $recipientEmail = $event['email'] ?? $event['recipient'] ?? $event['recipient_email'] ?? null;
            $smtp2goId = $event['smtp2go_id'] ?? $event['id'] ?? null;

            // Extract email_message_id from custom headers
            $emailMessageId = $this->extractEmailMessageId($event);

            // Find lead by recipient email
            $leadId = null;
            if ($recipientEmail) {
                $lead = Lead::where('email', $recipientEmail)->first();
                $leadId = $lead?->id;
            }

            // Persist the raw event FIRST (audit trail before processing)
            $webhookEvent = WebhookEvent::create([
                'source' => 'smtp2go',
                'event_type' => $eventType ?? 'unknown',
                'recipient_email' => $recipientEmail,
                'smtp2go_id' => $smtp2goId,
                'email_message_id' => $emailMessageId,
                'lead_id' => $leadId,
                'payload' => $event,
                'processed' => false,
                'received_at' => now(),
            ]);

            // Process the event
            try {
                $this->processEvent($webhookEvent, $event);
                $webhookEvent->update(['processed' => true]);
                $processedCount++;
            } catch (\Throwable $e) {
                $webhookEvent->update([
                    'processed' => false,
                    'processing_notes' => substr($e->getMessage(), 0, 2000),
                ]);
                Log::error('Webhook processing failed', [
                    'webhook_event_id' => $webhookEvent->id,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Webhook processed.',
            'events_received' => count($events),
            'events_processed' => $processedCount,
        ], 200);
    }

    /**
     * Extract our email ID from custom headers in the webhook event.
     */
    private function extractEmailMessageId(array $event): ?int
    {
        $customHeaders = $event['custom_headers'] ?? [];

        foreach ($customHeaders as $header) {
            $hdr = $header['header'] ?? $header['name'] ?? '';
            $val = $header['value'] ?? $header['val'] ?? '';
            if (strtolower($hdr) === 'x-omni-os-email-id') {
                return (int) $val;
            }
        }

        return null;
    }

    /**
     * Process a single webhook event — update email tracking, handle bounces, etc.
     */
    private function processEvent(WebhookEvent $webhookEvent, array $event): void
    {
        $eventType = $event['event_type'] ?? $event['event'] ?? null;
        $emailMessageId = $webhookEvent->email_message_id;

        $email = $emailMessageId ? EmailMessage::find($emailMessageId) : null;

        // If no email matched by custom header, try matching by recipient
        if (! $email && $webhookEvent->lead_id) {
            $email = EmailMessage::where('lead_id', $webhookEvent->lead_id)
                ->where('status', 'sent')
                ->latest('sent_at')
                ->first();
        }

        match ($eventType) {
            'open', 'opened' => $email?->update(['opened_at' => now()]),
            'click', 'clicked' => $email?->update(['clicked_at' => now()]),
            'bounce', 'bounced', 'hard_bounce' => $this->handleBounce($email, $event),
            'complaint', 'spam' => $this->handleComplaint($email, $event),
            'unsubscribe' => $this->handleUnsubscribe($email, $event),
            'reply' => $this->handleReply($email, $event, $webhookEvent),
            'delivered', 'delivery' => null, // No action needed — just persisted
            default => null,
        };
    }

    protected function handleBounce(?EmailMessage $email, array $event): void
    {
        if (! $email) {
            return;
        }

        $reason = $event['reason'] ?? 'unknown';
        $recipient = $event['recipient'] ?? $event['email'] ?? $email->lead?->email;

        $email->update([
            'status' => 'failed',
            'error_message' => 'Bounced: '.$reason,
        ]);

        if ($recipient && $email->lead) {
            Suppression::firstOrCreate(
                ['brand_id' => $email->brand_id, 'email' => $recipient],
                ['reason' => 'hard_bounce', 'notes' => 'Bounced via SMTP2GO webhook: '.$reason],
            );
        }
    }

    protected function handleComplaint(?EmailMessage $email, array $event): void
    {
        if (! $email) {
            return;
        }

        $recipient = $event['recipient'] ?? $event['email'] ?? $email->lead?->email;

        $email->update([
            'status' => 'failed',
            'error_message' => 'Spam complaint',
        ]);

        if ($recipient && $email->lead) {
            Suppression::firstOrCreate(
                ['brand_id' => $email->brand_id, 'email' => $recipient],
                ['reason' => 'spam_complaint', 'notes' => 'Spam complaint via SMTP2GO'],
            );
        }
    }

    protected function handleUnsubscribe(?EmailMessage $email, array $event): void
    {
        if (! $email) {
            return;
        }

        $recipient = $event['recipient'] ?? $event['email'] ?? $email->lead?->email;

        $email->update([
            'status' => 'failed',
            'error_message' => 'Unsubscribed',
        ]);

        if ($recipient && $email->lead) {
            Suppression::firstOrCreate(
                ['brand_id' => $email->brand_id, 'email' => $recipient],
                ['reason' => 'unsubscribe', 'notes' => 'Unsubscribed via SMTP2GO list-unsubscribe'],
            );
        }
    }

    /**
     * Handle a reply event from SMTP2GO.
     * Creates a Reply record (visible in inbox) and logs to Activity Feed.
     * Classification is left to Hermes (POST /api/v1/replies).
     */
    protected function handleReply(?EmailMessage $email, array $event, WebhookEvent $webhookEvent): void
    {
        $lead = $email?->lead ?? Lead::find($webhookEvent->lead_id);
        if (! $lead) {
            return;
        }

        $replyText = $event['plain_text_body']
            ?? $event['text']
            ?? $event['body']
            ?? '(no content)';

        $replySubject = $event['subject'] ?? '(no subject)';
        $fromEmail = $event['from'] ?? $event['sender'] ?? $lead->email ?? '';

        // Create a Reply record (visible in the inbox)
        Reply::create([
            'lead_id' => $lead->id,
            'brand_id' => $lead->brand_id,
            'email_message_id' => $email?->id,
            'from_email' => $fromEmail,
            'subject' => $replySubject,
            'body' => substr($replyText, 0, 10000),
            'body_html' => $event['html_body'] ?? null,
            'classification' => 'unclassified',
            'direction' => 'inbound',
            'read' => false,
            'received_at' => now(),
        ]);

        // Also store in raw_data for backward compatibility
        $raw = $lead->raw_data ?? [];
        $raw['incoming_replies'] = $raw['incoming_replies'] ?? [];
        $raw['incoming_replies'][] = [
            'email_message_id' => $email?->id,
            'subject' => $replySubject,
            'body' => substr($replyText, 0, 5000),
            'received_at' => now()->toIso8601String(),
            'classified' => false,
        ];
        $lead->updateQuietly(['raw_data' => $raw]);

        // Log to activity feed
        app(ActivityLogger::class)->log([
            'brand_id' => $lead->brand_id,
            'source' => 'smtp2go.webhook.reply',
            'event_type' => 'reply_classified',
            'title' => "Reply received — {$lead->company_name} (pending classification)",
            'body' => substr($replyText, 0, 500),
            'metadata' => [
                'lead_id' => $lead->id,
                'lead_name' => $lead->company_name,
                'email_message_id' => $email?->id,
                'subject' => $replySubject,
                'classified' => false,
            ],
            'severity' => 'info',
        ]);
    }
}
