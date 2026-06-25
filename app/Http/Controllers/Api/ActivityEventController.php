<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityEventRequest;
use App\Models\Agent;
use App\Models\Brand;
use App\Services\ActivityLogger;

class ActivityEventController extends Controller
{
    public function store(StoreActivityEventRequest $request, ActivityLogger $logger)
    {
        $data = $request->validated();

        // Resolve brand_slug to brand_id
        $brand = null;
        if ($data['brand_slug'] ?? null) {
            $brand = Brand::where('slug', $data['brand_slug'])->first();
        }

        // Determine agent_id: explicit override > token-derived > null
        $agentId = null;
        if (! empty($data['agent_codename'])) {
            $agent = Agent::where('codename', $data['agent_codename'])->where('is_active', true)->first();
            if ($agent) {
                $agentId = $agent->id;
            }
        } elseif (app()->bound('currentAgent')) {
            $agentId = app('currentAgent')->id;
        }

        $event = $logger->log([
            'brand_id' => $brand?->id,
            'agent_id' => $agentId,
            'source' => $data['source'],
            'event_type' => $data['event_type'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'severity' => $data['severity'],
            'notify_telegram' => $data['notify_telegram'] ?? false,
        ]);

        return response()->json([
            'id' => $event->id,
            'message' => 'Event logged.',
        ], 201);
    }
}
