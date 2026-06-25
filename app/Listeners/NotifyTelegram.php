<?php

namespace App\Listeners;

use App\Events\ActivityEventCreated;
use App\Services\TelegramService;

class NotifyTelegram
{
    /**
     * Send activity events to Telegram when notify_telegram flag is set.
     */
    public function handle(ActivityEventCreated $event): void
    {
        $telegram = app(TelegramService::class);

        if (! $telegram->isConfigured()) {
            return;
        }

        $entry = $event->event;
        $severityIcon = match ($entry->severity) {
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            default => 'ℹ️',
        };

        $text = "{$severityIcon} <b>{$entry->title}</b>\n";
        $text .= "<code>{$entry->source}</code>\n";

        if ($entry->body) {
            $text .= "\n{$entry->body}\n";
        }

        if ($entry->metadata) {
            $meta = is_array($entry->metadata) ? $entry->metadata : json_decode($entry->metadata, true) ?? [];
            foreach ($meta as $key => $val) {
                $text .= "\n{$key}: ".(is_array($val) ? json_encode($val) : $val);
            }
        }

        $telegram->sendMessage($text);
    }
}
