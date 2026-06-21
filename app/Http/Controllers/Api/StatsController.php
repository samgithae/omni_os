<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Suppression;

class StatsController extends Controller
{
    public function index()
    {
        $brandSlug = request('brand_slug');

        $leadsQuery = Lead::query();
        $suppressionQuery = Suppression::query();
        $emailQuery = EmailMessage::query();

        if ($brandSlug) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $leadsQuery->where('brand_id', $brand->id);
                $suppressionQuery->where('brand_id', $brand->id);
                $emailQuery->where('brand_id', $brand->id);
            }
        }

        $totalLeads = $leadsQuery->count();

        $leadsByStatus = (clone $leadsQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $leadsBySegment = (clone $leadsQuery)
            ->selectRaw('segment, COUNT(*) as count')
            ->groupBy('segment')
            ->pluck('count', 'segment')
            ->toArray();

        $suppressions = $suppressionQuery->count();

        $emailsPendingApproval = (clone $emailQuery)
            ->where('approval_status', 'pending')
            ->count();

        $emailsSent = (clone $emailQuery)
            ->where('status', 'sent')
            ->count();

        $topCities = (clone $leadsQuery)
            ->selectRaw('city, COUNT(*) as count')
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'city')
            ->toArray();

        $recentEvents = LeadEvent::query()
            ->when($brandSlug, function ($q) use ($brandSlug) {
                $brand = Brand::where('slug', $brandSlug)->first();
                if ($brand) {
                    $q->where('brand_id', $brand->id);
                }
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'created_at' => $e->created_at->toIso8601String(),
            ]);

        return response()->json([
            'total_leads' => $totalLeads,
            'by_status' => $leadsByStatus,
            'by_segment' => $leadsBySegment,
            'suppressions' => $suppressions,
            'emails_pending_approval' => $emailsPendingApproval,
            'emails_sent' => $emailsSent,
            'top_cities' => $topCities,
            'recent_events' => $recentEvents,
        ]);
    }
}
