<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\LeadEvent;
use App\Models\SequenceSchedule;
use Illuminate\Http\Request;

class EmailMessageApiController extends Controller
{
    /**
     * Get email messages that need content (approval_status = needs_content).
     * Includes context from the previous email so Hermes can write a coherent follow-up.
     */
    public function needsContent(Request $request)
    {
        $query = EmailMessage::with([
            'lead:id,company_name,email,segment,raw_data',
            'brand:id,name,slug',
        ])->needsContent();

        if ($request->filled('brand_slug')) {
            $brand = Brand::where('slug', $request->brand_slug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        $messages = $query->limit(50)->get()->map(function ($email) {
            // Get previous sent email for context
            $previous = EmailMessage::where('lead_id', $email->lead_id)
                ->where('sequence_step', $email->sequence_step - 1)
                ->where('status', 'sent')
                ->first();

            $schedule = SequenceSchedule::where('brand_id', $email->brand_id)
                ->where('segment', $email->lead?->segment)
                ->where('step', $email->sequence_step)
                ->first();

            return [
                'id' => $email->id,
                'lead_id' => $email->lead_id,
                'lead_name' => $email->lead?->company_name,
                'lead_email' => $email->lead?->email,
                'brand_slug' => $email->brand?->slug,
                'segment' => $email->lead?->segment,
                'sequence_step' => $email->sequence_step,
                'purpose' => $schedule?->purpose,
                'previous_subject' => $previous?->subject,
                'previous_sent_at' => $previous?->sent_at?->toIso8601String(),
                'raw_data' => $email->lead?->raw_data,
            ];
        });

        return response()->json(['messages' => $messages]);
    }

    /**
     * Fill content for an email message (subject + body).
     * Auto-transitions approval_status from needs_content → pending.
     */
    public function updateContent(Request $request, EmailMessage $emailMessage)
    {
        if ($emailMessage->approval_status !== 'needs_content') {
            return response()->json([
                'message' => 'Email is not in needs_content state.',
                'current_status' => $emailMessage->approval_status,
            ], 422);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string'],
        ]);

        $emailMessage->update([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
        ]);

        // Auto-transition to pending
        $emailMessage->markContentReady();

        // Log lead event
        LeadEvent::create([
            'lead_id' => $emailMessage->lead_id,
            'brand_id' => $emailMessage->brand_id,
            'event_type' => 'email_drafted',
            'source' => 'hermes.drafting-api',
            'payload' => [
                'email_message_id' => $emailMessage->id,
                'sequence_step' => $emailMessage->sequence_step,
                'subject' => $validated['subject'],
            ],
        ]);

        return response()->json([
            'message' => 'Content filled and transitioned to pending approval.',
            'email_message' => [
                'id' => $emailMessage->id,
                'subject' => $emailMessage->subject,
                'approval_status' => $emailMessage->approval_status,
                'status' => $emailMessage->status,
            ],
        ]);
    }
}
