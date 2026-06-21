<?php

namespace App\Listeners;

use App\Events\ActivityEventCreated;

class NotifyTelegram
{
    /**
     * Stub — Telegram integration is not yet wired into Laravel.
     * When it is, implement actual delivery here.
     */
    public function handle(ActivityEventCreated $event): void
    {
        // Future: send to Telegram channel
    }
}
