<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassifiedReplyRequest;
use App\Models\Reply;
use App\Services\ReplyService;

class ReplyController extends Controller
{
    /**
     * Receive a classified reply from Hermes and route it to its outcome.
     *
     * Also creates/updates a Reply record so the reply is visible in the inbox.
     *
     * Expects:
     *   - email_message_id: the sent email the user replied to
     *   - lead_id: the lead who replied
     *   - classification: interested|not_interested|out_of_office|unsubscribe|bounce
     *   - summary: human-readable summary of the reply
     *   - reply_body: the full reply text (optional)
     *   - confidence: 0-1 confidence score (optional)
     */
    public function store(StoreClassifiedReplyRequest $request, ReplyService $replyService)
    {
        $data = $request->validated();

        // Find the existing unclassified Reply record for this email_message_id (if any)
        $reply = Reply::where('email_message_id', $data['email_message_id'])
            ->where('direction', 'inbound')
            ->latest()
            ->first();

        if ($reply) {
            // Update the existing record with the classification
            $reply->update([
                'classification' => $data['classification'],
                'classification_summary' => $data['summary'],
                'classification_confidence' => $data['confidence'] ? (string) $data['confidence'] : null,
                'body' => $data['reply_body'] ?? $reply->body,
            ]);
        } else {
            // No existing Reply record — create one (Hermes may classify before webhook fires, or webhook didn't create one)
            $emailMessage = \App\Models\EmailMessage::find($data['email_message_id']);
            $lead = \App\Models\Lead::find($data['lead_id']);

            $reply = Reply::create([
                'lead_id' => $data['lead_id'],
                'brand_id' => $lead?->brand_id ?? $emailMessage?->brand_id,
                'email_message_id' => $data['email_message_id'],
                'from_email' => $lead?->email ?? '',
                'subject' => $emailMessage?->subject,
                'body' => $data['reply_body'] ?? '(no body provided)',
                'classification' => $data['classification'],
                'classification_summary' => $data['summary'],
                'classification_confidence' => $data['confidence'] ? (string) $data['confidence'] : null,
                'direction' => 'inbound',
                'read' => false,
                'received_at' => now(),
            ]);
        }

        // Route the reply to its outcome (Telegram alert, suppression, lead status, etc.)
        $result = $replyService->route($data);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json(array_merge($result, [
            'reply_id' => $reply->id,
        ]), 201);
    }
}