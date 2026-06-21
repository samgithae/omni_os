<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Lead;
use App\Models\Suppression;
use Illuminate\Http\Request;
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
            'phone' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'confidence' => ['nullable', 'array'],
            'enrichment_source' => ['nullable', 'string', 'max:100'],
            'enrichment_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['email']) && $validated['email'] === null) {
            // No email found — increment attempts
            $lead->increment('enrichment_attempts');
            $lead->update(['enrichment_notes' => $validated['enrichment_notes'] ?? null]);

            if ($lead->enrichment_attempts >= 3) {
                $lead->transitionTo(LeadStatus::NoEmailFound, 'api.enrich');
            }

            return response()->json([
                'message' => 'No email found. Attempt ' . $lead->enrichment_attempts . ' recorded.',
                'lead_id' => $lead->id,
                'enrichment_attempts' => $lead->enrichment_attempts,
            ]);
        }

        $updateData = [];

        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
            $updateData['email_verified'] = $validated['email_verified'] ?? false;
        }

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

        // Transition to enriched
        $lead->transitionTo(LeadStatus::Enriched, 'api.enrich');

        return response()->json([
            'message' => 'Lead enriched successfully.',
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'email' => $lead->email,
            'email_verified' => $lead->email_verified,
        ]);
    }
}
