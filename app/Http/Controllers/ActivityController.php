<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityEvent::with('brand');

        // Brand filter
        if ($request->filled('brand')) {
            $query->whereHas('brand', fn($q) => $q->where('slug', $request->brand));
        }

        $events = $query->latest()
            ->take(30)
            ->get()
            ->groupBy(fn($e) => $e->created_at->isToday() ? 'Today'
                : ($e->created_at->isYesterday() ? 'Yesterday'
                    : $e->created_at->format('F j, Y')));

        $brands = Brand::where('is_active', true)->get(['id', 'name', 'slug', 'color']);

        // Latest event id for polling
        $latestId = ActivityEvent::max('id');

        return Inertia::render('Activity', [
            'groupedEvents' => $events,
            'brands' => $brands,
            'filters' => $request->only(['brand']),
            'latestId' => $latestId,
        ]);
    }

    public function poll(Request $request)
    {
        $request->validate([
            'since' => 'required|integer|min:0',
        ]);

        $query = ActivityEvent::where('id', '>', $request->since);

        if ($request->filled('brand')) {
            $query->whereHas('brand', fn($q) => $q->where('slug', $request->brand));
        }

        $newCount = $query->count();
        $latestId = ActivityEvent::max('id');

        return response()->json([
            'new_count' => $newCount,
            'latest_id' => $latestId ?? $request->since,
        ]);
    }

    public function loadMore(Request $request)
    {
        $request->validate([
            'before' => 'required|integer|min:1',
        ]);

        $query = ActivityEvent::with('brand')->where('id', '<', $request->before);

        if ($request->filled('brand')) {
            $query->whereHas('brand', fn($q) => $q->where('slug', $request->brand));
        }

        $events = $query->latest()->take(20)->get();

        return response()->json([
            'events' => $events->map(fn($e) => [
                'id' => $e->id,
                'source' => $e->source,
                'event_type' => $e->event_type,
                'title' => $e->title,
                'body' => $e->body,
                'metadata' => $e->metadata,
                'severity' => $e->severity,
                'brand' => $e->brand ? [
                    'id' => $e->brand->id,
                    'name' => $e->brand->name,
                    'slug' => $e->brand->slug,
                    'color' => $e->brand->color,
                ] : null,
                'created_at' => $e->created_at->toIso8601String(),
                'relative_time' => $e->created_at->diffForHumans(),
            ]),
            'has_more' => $events->count() === 20,
        ]);
    }
}
