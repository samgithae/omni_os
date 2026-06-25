<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EmailController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailMessage::query()->with(['lead:id,company_name,email', 'brand:id,name,slug']);

        if ($brandSlug = $request->get('brand_slug')) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($approvalStatus = $request->get('approval_status')) {
            $query->where('approval_status', $approvalStatus);
        }

        if ($step = $request->get('sequence_step')) {
            $query->where('sequence_step', (int) $step);
        }

        $limit = min((int) $request->get('limit', 50), 500);

        return response()->json(
            $query->orderByDesc('id')->paginate($limit)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_slug' => ['required', 'string', 'exists:brands,slug'],
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'sequence_step' => ['required', 'integer', 'min:1', 'max:10'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $brand = Brand::where('slug', $validated['brand_slug'])->firstOrFail();
        $lead = Lead::findOrFail($validated['lead_id']);

        // Check if a draft already exists for this lead + step (idempotency)
        $existing = EmailMessage::query()
            ->where('lead_id', $lead->id)
            ->where('sequence_step', $validated['sequence_step'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Email draft already exists for this lead and sequence step.',
                'email_id' => $existing->id,
                'approval_status' => $existing->approval_status,
            ], 409);
        }

        // Check suppression
        if ($lead->isSuppressed()) {
            $lead->transitionTo(LeadStatus::Suppressed, 'api.email.draft');

            return response()->json([
                'message' => 'Lead email is suppressed. Cannot draft.',
                'lead_id' => $lead->id,
            ], 422);
        }

        $email = EmailMessage::create([
            'brand_id' => $brand->id,
            'lead_id' => $lead->id,
            'sequence_step' => $validated['sequence_step'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Email draft created.',
            'email_id' => $email->id,
            'approval_status' => 'pending',
        ], 201);
    }

    public function approve(Request $request, EmailMessage $email)
    {
        if ($email->approval_status !== 'pending') {
            return response()->json([
                'message' => "Email is already {$email->approval_status}. Cannot approve.",
                'email_id' => $email->id,
            ], 422);
        }

        $notes = $request->get('notes');
        $email->approve($notes);

        return response()->json([
            'message' => 'Email approved and queued for sending.',
            'email_id' => $email->id,
            'status' => $email->status,
        ]);
    }

    public function reject(Request $request, EmailMessage $email)
    {
        if ($email->approval_status !== 'pending') {
            return response()->json([
                'message' => "Email is already {$email->approval_status}. Cannot reject.",
                'email_id' => $email->id,
            ], 422);
        }

        $notes = $request->get('notes');
        $email->reject($notes);

        return response()->json([
            'message' => 'Email rejected.',
            'email_id' => $email->id,
        ]);
    }

    public function sendBatch(Request $request)
    {
        $validated = $request->validate([
            'brand_slug' => ['nullable', 'string', 'exists:brands,slug'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'] ?? 20;

        $query = EmailMessage::query()
            ->where('approval_status', 'approved')
            ->where('status', 'queued')
            ->with(['lead:id,company_name,email,contact_name', 'brand:id,name,slug']);

        if ($brandSlug = $validated['brand_slug'] ?? null) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        $emails = $query->limit($limit)->get();

        if ($emails->isEmpty()) {
            return response()->json([
                'message' => 'No queued emails to send.',
                'sent' => 0,
                'failed' => 0,
            ]);
        }

        $apiKey = config('services.smtp2go.api_key');
        $apiEndpoint = config('services.smtp2go.api_endpoint', 'https://api.smtp2go.com/v3');

        if (! $apiKey) {
            return response()->json([
                'message' => 'SMTP2GO API key not configured.',
                'sent' => 0,
                'failed' => count($emails),
            ], 500);
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($emails as $email) {
            try {
                $lead = $email->lead;
                $brand = $email->brand;

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Smtp2go-Api-Key' => $apiKey,
                ])->post(rtrim($apiEndpoint, '/').'/email/send', [
                    'to' => [$lead->email],
                    'sender' => config('mail.from.address'),
                    'sender_name' => config('mail.from.name'),
                    'subject' => $email->subject,
                    'html_body' => nl2br(e($email->body)),
                    'text_body' => $email->body,
                    'custom_headers' => [
                        ['header' => 'X-Omni-OS-Email-ID', 'value' => (string) $email->id],
                    ],
                ]);

                if ($response->successful()) {
                    $body = $response->json();
                    $email->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    // Transition lead to emailed
                    try {
                        $lead->transitionTo(LeadStatus::Emailed, 'api.email.send', [
                            'email_id' => $email->id,
                            'sequence_step' => $email->sequence_step,
                        ]);
                    } catch (\Throwable $transitionError) {
                        // Lead transition may fail if already in a terminal state; log and continue
                    }

                    $sent++;
                } else {
                    $errorMsg = $response->body();
                    $email->update([
                        'status' => 'failed',
                        'error_message' => substr($errorMsg, 0, 500),
                    ]);
                    $errors[] = ['email_id' => $email->id, 'error' => $errorMsg];
                    $failed++;
                }
            } catch (\Throwable $e) {
                $email->update([
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
                $errors[] = ['email_id' => $email->id, 'error' => $e->getMessage()];
                $failed++;
            }
        }

        return response()->json([
            'message' => "Sent {$sent}, failed {$failed}.",
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }
}
