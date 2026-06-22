<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Models\ActivityEventComment;
use App\Models\Brand;
use App\Jobs\RespondToComment;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityEvent::with('brand')
            ->with('comments')
            ->withCount('comments');

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

    /**
     * Post a comment as Sam (web session).
     */
    public function storeComment(Request $request, ActivityEvent $event)
    {
        $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_instruction' => ['nullable', 'boolean'],
        ]);

        $isInstruction = $request->boolean('is_instruction');

        $comment = $event->comments()->create([
            'author' => 'human',
            'body' => $request->body,
            'is_instruction' => $isInstruction,
            'instruction_status' => $isInstruction ? 'pending' : null,
        ]);

        // Dispatch Hermes response job (runs in queue, posts back as Hermes)
        RespondToComment::dispatch($event, $comment);

        // Log to activity feed if it's an instruction
        if ($isInstruction) {
            app(ActivityLogger::class)->log([
                'brand_id' => $event->brand_id,
                'source' => 'activity-feed.comment',
                'event_type' => 'system',
                'title' => "Sam flagged instruction on: {$event->title}",
                'body' => substr($request->body, 0, 500),
                'metadata' => [
                    'event_id' => $event->id,
                    'comment_id' => $comment->id,
                    'is_instruction' => true,
                ],
                'severity' => 'info',
            ]);
        }

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'author' => $comment->author,
                'body' => $comment->body,
                'is_instruction' => $comment->is_instruction,
                'instruction_status' => $comment->instruction_status,
                'created_at' => $comment->created_at->toIso8601String(),
                'relative_time' => $comment->created_at->diffForHumans(),
            ],
        ], 201);
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

        $query = ActivityEvent::with('brand')
            ->with('comments')
            ->withCount('comments')
            ->where('id', '<', $request->before);

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
                'comments_count' => $e->comments_count,
                'comments' => $e->comments->map(fn($c) => [
                    'id' => $c->id,
                    'author' => $c->author,
                    'body' => $c->body,
                    'is_instruction' => $c->is_instruction,
                    'instruction_status' => $c->instruction_status,
                    'created_at' => $c->created_at->toIso8601String(),
                    'relative_time' => $c->created_at->diffForHumans(),
                ]),
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