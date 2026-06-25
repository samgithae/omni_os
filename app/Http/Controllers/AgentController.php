<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::ordered()
            ->withCount(['activityEvents as actions_this_week' => function ($query) {
                $query->where('created_at', '>=', now()->subWeek());
            }])
            ->get()
            ->map(function (Agent $agent) {
                $recentEvents = $agent->activityEvents()
                    ->with('brand')
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn ($event) => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'event_type' => $event->event_type,
                        'severity' => $event->severity,
                        'brand' => $event->brand ? [
                            'name' => $event->brand->name,
                            'slug' => $event->brand->slug,
                            'color' => $event->brand->color,
                        ] : null,
                        'created_at' => $event->created_at->toIso8601String(),
                        'relative_time' => $event->created_at->diffForHumans(),
                    ]);

                return [
                    'id' => $agent->id,
                    'codename' => $agent->codename,
                    'display_name' => $agent->display_name,
                    'role' => $agent->role,
                    'description' => $agent->description,
                    'avatar_url' => $agent->avatar_url,
                    'function_area' => $agent->function_area,
                    'status' => $agent->status,
                    'is_active' => $agent->is_active,
                    'last_active_at' => $agent->last_active_at?->diffForHumans(),
                    'actions_this_week' => (int) $agent->actions_this_week,
                    'recent_events' => $recentEvents,
                    'documents' => $agent->documents->map(fn ($doc) => [
                        'id' => $doc->id,
                        'label' => $doc->label,
                        'url' => $doc->url,
                        'mime_type' => $doc->mime_type,
                        'size_bytes' => $doc->size_bytes,
                    ]),
                ];
            });

        return Inertia::render('Agents', [
            'agents' => $agents,
        ]);
    }
}
