<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityEventRequest;
use App\Services\ActivityLogger;

class ActivityEventController extends Controller
{
    public function store(StoreActivityEventRequest $request, ActivityLogger $logger)
    {
        $data = $request->validated();

        // Resolve brand_slug to brand_id
        $brand = null;
        if ($data['brand_slug'] ?? null) {
            $brand = \App\Models\Brand::where('slug', $data['brand_slug'])->first();
        }

        $event = $logger->log([
            'brand_id' => $brand?->id,
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
