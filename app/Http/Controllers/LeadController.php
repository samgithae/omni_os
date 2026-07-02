<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::with(['brand', 'emailMessages']);

        // Brand filter
        if ($request->filled('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $request->brand));
        }

        // Segment filter
        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Score tier filter
        if ($request->filled('tier')) {
            match ($request->tier) {
                'hot' => $query->where('score', '>=', 80),
                'warm' => $query->whereBetween('score', [60, 79]),
                'moderate' => $query->whereBetween('score', [40, 59]),
                'cold' => $query->whereBetween('score', [20, 39]),
                'frigid' => $query->where('score', '<', 20),
                default => null,
            };
        }

        // City filter
        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        // Has email filter
        if ($request->filled('has_email')) {
            if ($request->has_email === 'yes') {
                $query->whereNotNull('email');
            } else {
                $query->whereNull('email');
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhere('category', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'score');
        $direction = $request->get('direction', 'desc');

        $allowedSorts = ['score', 'company_name', 'created_at', 'email', 'segment', 'status', 'city'];
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'score';
        }
        $direction = in_array($direction, ['asc', 'desc']) ? $direction : 'desc';

        $leads = $query->orderBy($sort, $direction)
            ->paginate(25)
            ->through(function ($lead) {
                $emailMessages = $lead->emailMessages ?? collect();

                return [
                    'id' => $lead->id,
                    'company_name' => $lead->company_name,
                    'contact_name' => $lead->contact_name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'website' => $lead->website,
                    'segment' => $lead->segment,
                    'status' => $lead->status,
                    'category' => $lead->category,
                    'subcategory' => $lead->subcategory,
                    'city' => $lead->city,
                    'country' => $lead->country,
                    'score' => $lead->score,
                    'score_tier' => $lead->scoreTier(),
                    'email_confidence' => $lead->email_confidence,
                    'enrichment_attempts' => $lead->enrichment_attempts,
                    'email_verified' => $lead->email_verified,
                    'emails_sent' => $emailMessages->where('status', 'sent')->count(),
                    'emails_opened' => $emailMessages->whereNotNull('opened_at')->count(),
                    'emails_clicked' => $emailMessages->whereNotNull('clicked_at')->count(),
                    'total_emails' => $emailMessages->count(),
                    'brand' => $lead->brand ? [
                        'id' => $lead->brand->id,
                        'name' => $lead->brand->name,
                        'slug' => $lead->brand->slug,
                        'color' => $lead->brand->color,
                    ] : null,
                    'created_at' => $lead->created_at?->toIso8601String(),
                ];
            });

        // Aggregate stats
        $stats = $this->buildStats();

        // Available cities for filter
        $cities = Lead::select('city')
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->filter()
            ->values()
            ->toArray();

        $brands = Brand::where('is_active', true)->get(['id', 'name', 'slug', 'color']);

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => $request->only(['brand', 'segment', 'status', 'tier', 'city', 'has_email', 'search', 'sort', 'direction']),
            'brands' => $brands,
            'cities' => $cities,
        ]);
    }

    private function buildStats(): array
    {
        $total = Lead::count();
        $withEmail = Lead::whereNotNull('email')->count();
        $enriched = Lead::where('status', 'enriched')->count();
        $suppressed = Lead::where('status', 'suppressed')->count();

        $avgScore = $total > 0 ? round(Lead::avg('score'), 1) : 0;
        $maxScore = Lead::max('score') ?? 0;

        // Tier distribution
        $tierCounts = [
            'hot' => Lead::where('score', '>=', 80)->count(),
            'warm' => Lead::whereBetween('score', [60, 79])->count(),
            'moderate' => Lead::whereBetween('score', [40, 59])->count(),
            'cold' => Lead::whereBetween('score', [20, 39])->count(),
            'frigid' => Lead::where('score', '<', 20)->count(),
        ];

        // Segment counts
        $segmentCounts = [
            'rabbit' => Lead::where('segment', 'rabbit')->count(),
            'deer' => Lead::where('segment', 'deer')->count(),
            'mouse' => Lead::where('segment', 'mouse')->count(),
            'elephant' => Lead::where('segment', 'elephant')->count(),
        ];

        // Status counts
        $statusCounts = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => $total,
            'with_email' => $withEmail,
            'enriched' => $enriched,
            'suppressed' => $suppressed,
            'avg_score' => $avgScore,
            'max_score' => $maxScore,
            'tier_counts' => $tierCounts,
            'segment_counts' => $segmentCounts,
            'status_counts' => $statusCounts,
        ];
    }
}
