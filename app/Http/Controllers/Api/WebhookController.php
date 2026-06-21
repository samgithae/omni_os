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
                'bounce', 'bounced', 'hard_bounce' => $email->update([
                    'status' => 'failed',
                    'error_message' => 'Bounced: '.($event['reason'] ?? 'unknown'),
                ]),
                'complaint', 'spam' => $email->update([
                    'status' => 'failed',
                    'error_message' => 'Spam complaint',
                ]),
                'unsubscribe' => $email->update([
                    'status' => 'failed',
                    'error_message' => 'Unsubscribed',
                ]),
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
}
