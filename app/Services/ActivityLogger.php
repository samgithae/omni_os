<?php

namespace App\Services;

use App\Enums\ActivityEventType;
use App\Enums\ActivitySeverity;
use App\Events\ActivityEventCreated;
use App\Models\ActivityEvent;

class ActivityLogger
{
    public function log(array $data): ActivityEvent
    {
        $event = ActivityEvent::create([
            'brand_id' => $data['brand_id'] ?? null,
            'agent_id' => $data['agent_id'] ?? null,
            'source' => $data['source'],
            'event_type' => $data['event_type'] instanceof ActivityEventType
                ? $data['event_type']->value : $data['event_type'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'severity' => $data['severity'] ?? ActivitySeverity::Info->value,
        ]);

        if ($data['notify_telegram'] ?? false) {
            event(new ActivityEventCreated($event));
        }

        return $event;
    }
}
