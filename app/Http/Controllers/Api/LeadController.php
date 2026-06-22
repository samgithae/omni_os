<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Suppression;
use App\Services\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query()->with('brand:id,name,slug');

        if ($brandSlug = $request->get('brand_slug')) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($segment = $request->get('segment')) {
            $query->where('segment', $segment);
        }

        if ($city = $request->get('city')) {
            $query->where('city', $city);
        }

        if ($country = $request->get('country')) {
            $query->where('country', $country);
        }

        $limit = min((int) $request->get('limit', 50), 500);

        return response()->json(
            $query->orderByDesc('id')->paginate($limit)
        );
    }

    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'brand_slug' => ['required', 'string', 'exists:brands,slug'],
            'leads' => ['required', 'array', 'max:100'],
            'leads.*.company_name' => ['required', 'string', 'max:255'],
            'leads.*.email' => ['nullable', 'email', 'max:255'],
            'leads.*.phone' => ['nullable', 'string', 'max:60'],
            'leads.*.website' => ['nullable', 'url', 'max:500'],
            'leads.*.segment' => ['nullable', 'string', 'in:rabbit,deer'],
            'leads.*.category' => ['nullable', 'string', 'max:255'],
            'leads.*.country' => ['nullable', 'string', 'max:100'],
            'leads.*.city' => ['nullable', 'string', 'max:255'],
            'leads.*.address' => ['nullable', 'string', 'max:500'],
            'leads.*.source' => ['nullable', 'string', 'max:100'],
            'leads.*.source_url' => ['nullable', 'string', 'max:1000'],
            'leads.*.confidence' => ['nullable', 'array'],
        ]);

        $brand = Brand::where('slug', $validated['brand_slug'])->firstOrFail();

        $created = 0;
        $duplicates = 0;
        $suppressed = 0;
        $validationErrors = [];
        $errors = [];

        foreach ($validated['leads'] as $index => $leadData) {
            // Check suppression first
            if (! empty($leadData['email'])) {
                $isSuppressed = Suppression::query()
                    ->where('brand_id', $brand->id)
                    ->where('email', $leadData['email'])
                    ->exists();

                if ($isSuppressed) {
                    $suppressed++;
                    continue;
                }
            }

            // Check duplicate by brand + email (or brand + company_name if no email)
            $dedupQuery = Lead::query()->where('brand_id', $brand->id);

            if (! empty($leadData['email'])) {
                $dedupQuery->where('email', $leadData['email']);
            } else {
                $dedupQuery->where('company_name', $leadData['company_name']);
            }

            if ($dedupQuery->exists()) {
                $duplicates++;
                continue;
            }

            try {
                $confidence = $leadData['confidence'] ?? [];

                Lead::create([
                    'brand_id' => $brand->id,
                    'company_name' => $leadData['company_name'],
                    'email' => $leadData['email'] ?? null,
                    'phone' => $leadData['phone'] ?? null,
                    'website' => $leadData['website'] ?? null,
                    'segment' => $leadData['segment'] ?? 'rabbit',
                    'category' => $leadData['category'] ?? null,
                    'country' => $leadData['country'] ?? 'Kenya',
                    'city' => $leadData['city'] ?? null,
                    'address' => $leadData['address'] ?? null,
                    'source' => $leadData['source'] ?? 'api',
                    'source_url' => $leadData['source_url'] ?? null,
                    'status' => LeadStatus::New->value,
                    'raw_data' => array_filter([
                        'confidence' => $confidence,
                        'import_source' => 'hermes_agent_api',
                        'imported_at' => now()->toIso8601String(),
                    ]),
                ]);

                $created++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'index' => $index,
                    'company' => $leadData['company_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'created' => $created,
            'duplicates' => $duplicates,
            'suppressed' => $suppressed,
            'errors' => $errors,
        ], empty($errors) ? 201 : 207);
    }

    public function enrich(Request $request, Lead $lead)
    {
        // Don't re-enrich terminal states
        if ($lead->statusEnum()->isTerminal()) {
            return response()->json([
                'message' => "Lead is in terminal state [{$lead->status}]. Cannot enrich.",
                'lead_id' => $lead->id,
            ], 422);
        }

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'email_verified' => ['nullable', 'boolean'],
            'email_confidence' => ['nullable', 'string', 'in:verified,inferred,estimated,unavailable'],
            'phone' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'confidence' => ['nullable', 'array'],
            'enrichment_source' => ['nullable', 'string', 'max:100'],
            'enrichment_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['email']) && $validated['email'] === null) {
            // No email found — increment attempts
            $lead->enrichNoEmail(
                maxAttempts: 3,
                source: 'api.enrich',
                notes: $validated['enrichment_notes'] ?? null,
            );

            return response()->json([
                'message' => 'No email found. Attempt ' . $lead->enrichment_attempts . ' recorded.',
                'lead_id' => $lead->id,
                'enrichment_attempts' => $lead->enrichment_attempts,
                'status' => $lead->status,
            ]);
        }

        if (isset($validated['email'])) {
            $lead->enrichFound(
                email: $validated['email'],
                confidence: $validated['email_confidence'] ?? 'inferred',
                verified: $validated['email_verified'] ?? false,
                source: 'api.enrich',
                notes: $validated['enrichment_notes'] ?? null,
            );

            return response()->json([
                'message' => 'Lead enriched successfully.',
                'lead_id' => $lead->id,
                'status' => $lead->status,
                'email' => $lead->email,
                'email_verified' => $lead->email_verified,
                'email_confidence' => $lead->email_confidence,
            ]);
        }

        // Only phone/contact_name/notes were provided — partial enrichment
        $updateData = [];

        if (isset($validated['phone'])) {
            $updateData['phone'] = $validated['phone'];
        }

        if (isset($validated['contact_name'])) {
            $updateData['contact_name'] = $validated['contact_name'];
        }

        if (isset($validated['enrichment_source'])) {
            $updateData['source'] = $validated['enrichment_source'];
        }

        $updateData['raw_data'] = array_merge($lead->raw_data ?? [], [
            'enrichment' => [
                'confidence' => $validated['confidence'] ?? [],
                'source' => $validated['enrichment_source'] ?? null,
                'notes' => $validated['enrichment_notes'] ?? null,
                'enriched_at' => now()->toIso8601String(),
            ],
        ]);

        $lead->update($updateData);

        $lead->transitionTo(LeadStatus::Enriched, 'api.enrich');

        return response()->json([
            'message' => 'Lead enriched successfully.',
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'email' => $lead->email,
            'email_verified' => $lead->email_verified,
            'email_confidence' => $lead->email_confidence,
        ]);
    }

    /**
     * Recalculate the score for a single lead.
     */
    public function score(Lead $lead, LeadScoringService $scorer)
    {
        $oldScore = $lead->score;

        $lead->load(['emailMessages', 'events']);
        $result = $scorer->calculate($lead);

        $lead->score = $result['score'];
        $lead->saveQuietly();

        return response()->json([
            'message' => 'Lead score recalculated.',
            'lead_id' => $lead->id,
            'old_score' => $oldScore,
            'new_score' => $result['score'],
            'breakdown' => $result['breakdown'],
        ]);
    }

    /**
     * GET /api/v1/leads/needs-email-generation
     * Returns enriched leads that are missing one or more sequence steps.
     */
    public function needsEmailGeneration(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 10), 50);

        $query = Lead::query()
            ->where('status', 'enriched')
            ->whereNotNull('email')
            ->with(['brand', 'emailMessages']);

        if ($brandSlug = $request->get('brand_slug')) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug));
        }

        if ($segment = $request->get('segment')) {
            $query->where('segment', $segment);
        }

        $leads = $query->get();

        $results = [];

        foreach ($leads as $lead) {
            $config = BrandSequenceConfig::resolveFor($lead->brand_id, $lead->segment);

            if (! $config) {
                continue;
            }

            $requiredSteps = range(1, $config->sequence_steps);

            $existingSteps = $lead->emailMessages
                ->filter(fn ($m) => ! empty(trim($m->subject ?? '')) && ! empty(trim($m->body ?? '')))
                ->pluck('sequence_step')
                ->toArray();

            $missingSteps = array_values(array_diff($requiredSteps, $existingSteps));

            if (empty($missingSteps)) {
                continue;
            }

            $rawData = $lead->raw_data ?? [];

            $results[] = [
                'lead_id'        => $lead->id,
                'brand_id'       => $lead->brand_id,
                'brand_slug'     => $lead->brand->slug,
                'brand_name'     => $lead->brand->name,
                'segment'        => $lead->segment,
                'company_name'   => $lead->company_name,
                'display_name'   => $rawData['display_name'] ?? null,
                'concrete_fact'  => $rawData['concrete_fact'] ?? null,
                'category'       => $lead->category,
                'city'           => $lead->city,
                'country'        => $lead->country,
                'email'          => $lead->email,
                'website'        => $lead->website,
                'sequence_steps' => $config->sequence_steps,
                'existing_steps' => $existingSteps,
                'missing_steps'  => $missingSteps,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return response()->json([
            'count' => count($results),
            'leads' => $results,
        ]);
    }

    /**
     * POST /api/v1/leads/{lead}/email-content-batch
     * Hermes submits all N emails for a lead in one atomic call.
     * Rejects partial submissions.
     */
    public function submitEmailContentBatch(Request $request, Lead $lead): JsonResponse
    {
        // Only process enriched leads with an email
        if ($lead->status !== 'enriched' || ! $lead->email) {
            return response()->json(['error' => 'Lead must be in enriched status with an email address.'], 422);
        }

        // Suppression check
        if (Suppression::where('brand_id', $lead->brand_id)->where('email', $lead->email)->exists()) {
            return response()->json(['error' => 'Lead email is on the suppression list. Skipped.'], 422);
        }

        $config = BrandSequenceConfig::resolveFor($lead->brand_id, $lead->segment);

        if (! $config) {
            return response()->json(['error' => 'No active sequence config found for this brand+segment.'], 422);
        }

        $requiredSteps = range(1, $config->sequence_steps);

        $validated = $request->validate([
            'emails'             => ['required', 'array'],
            'emails.*.step'      => ['required', 'integer', 'min:1', 'max:10'],
            'emails.*.subject'   => ['required', 'string', 'max:255'],
            'emails.*.body'      => ['required', 'string'],
        ]);

        $submittedSteps = collect($validated['emails'])->pluck('step')->toArray();
        $missingSteps = array_values(array_diff($requiredSteps, $submittedSteps));

        // Completeness gate — ALL required steps must be in this batch
        if (! empty($missingSteps)) {
            return response()->json([
                'error'          => 'Incomplete batch — missing required sequence steps.',
                'required_steps' => $requiredSteps,
                'missing_steps'  => $missingSteps,
            ], 422);
        }

        // Basic content validation — no empty subjects or bodies
        foreach ($validated['emails'] as $email) {
            if (empty(trim($email['subject'])) || empty(trim($email['body']))) {
                return response()->json([
                    'error' => "Step {$email['step']} has empty subject or body.",
                ], 422);
            }
        }

        $createdSteps = [];

        DB::transaction(function () use ($lead, $validated, &$createdSteps) {
            foreach ($validated['emails'] as $email) {
                $emailMessage = EmailMessage::updateOrCreate(
                    [
                        'lead_id'       => $lead->id,
                        'sequence_step' => $email['step'],
                    ],
                    [
                        'brand_id'        => $lead->brand_id,
                        'subject'         => $email['subject'],
                        'body'            => $email['body'],
                        'status'          => 'draft',
                        'approval_status' => 'pending',
                    ]
                );

                $createdSteps[] = $email['step'];
            }

            // Log generation event
            \App\Models\LeadEvent::create([
                'lead_id'    => $lead->id,
                'brand_id'   => $lead->brand_id,
                'event_type' => 'email_sequence_generated',
                'payload'    => ['steps_generated' => collect($validated['emails'])->pluck('step')],
                'source'     => 'hermes',
            ]);
        });

        return response()->json([
            'success' => true,
            'lead_id' => $lead->id,
            'steps'   => $createdSteps,
            'message' => "Email sequence ({$config->sequence_steps} emails) ready for review.",
        ]);
    }
}
