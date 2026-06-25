<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Services\WinLossService;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(WinLossService $winloss)
    {
        $report = $winloss->report();

        // Score distribution for the analytics page
        $scoreDistribution = [
            'hot' => Lead::where('score', '>=', 80)->count(),
            'warm' => Lead::whereBetween('score', [60, 79])->count(),
            'moderate' => Lead::whereBetween('score', [40, 59])->count(),
            'cold' => Lead::whereBetween('score', [20, 39])->count(),
            'frigid' => Lead::where('score', '<', 20)->count(),
        ];

        $avgScore = Lead::count() > 0 ? round(Lead::avg('score'), 1) : 0;

        // Per-brand breakdown
        $brands = Brand::select('id', 'name', 'slug', 'color')
            ->withCount(['leads', 'suppressions'])
            ->get()
            ->map(function ($brand) {
                $sent = EmailMessage::where('brand_id', $brand->id)->where('status', 'sent')->count();
                $opened = EmailMessage::where('brand_id', $brand->id)->whereNotNull('opened_at')->count();
                $interested = Lead::where('brand_id', $brand->id)->where('status', 'interested')->count();
                $enriched = Lead::where('brand_id', $brand->id)->where('status', 'enriched')->count();

                return [
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'color' => $brand->color,
                    'leads_count' => $brand->leads_count,
                    'suppressions_count' => $brand->suppressions_count,
                    'enriched' => $enriched,
                    'sent' => $sent,
                    'opened' => $opened,
                    'interested' => $interested,
                ];
            });

        return Inertia::render('Analytics/Index', [
            'funnel' => $report['funnel'],
            'rates' => $report['rates'],
            'byCategory' => $report['by_category'],
            'byCity' => $report['by_city'],
            'bySegment' => $report['by_segment'],
            'byStep' => $report['by_step'],
            'replyOutcomes' => $report['reply_outcomes'],
            'scoreDistribution' => $scoreDistribution,
            'avgScore' => $avgScore,
            'brands' => $brands,
            'generatedAt' => $report['generated_at'],
        ]);
    }
}
