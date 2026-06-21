<?php

namespace App\Events;

use App\Models\ActivityEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityEventCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ActivityEvent $event;

    public function __construct(ActivityEvent $event)
    {
        $this->event = $event;
    }
}
