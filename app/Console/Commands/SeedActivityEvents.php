<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class SeedActivityEvents extends Command
{
    protected $signature = 'activity:seed-test-data';
    protected $description = 'Seed sample activity events for testing the feed';

    public function handle(ActivityLogger $logger): int
    {
        $this->info('Seeding test activity events...');

        $brands = Brand::all();
        $ujuzi = $brands->firstWhere('slug', 'ujuziplus');
        $hudu = $brands->firstWhere('slug', 'hudutech');

        $events = [
            ['brand_id' => $ujuzi->id, 'source' => 'hermes.ujuziplus.mining', 'event_type' => 'mining_run', 'title' => '23 new leads mined — Nairobi SACCOs', 'metadata' => ['count' => 23, 'category' => 'sacco', 'city' => 'nairobi'], 'severity' => 'success'],
            ['brand_id' => $ujuzi->id, 'source' => 'laravel.scheduler.enrichment', 'event_type' => 'enrichment_batch', 'title' => 'Enriched 35 of 40 leads — 5 no_email_found after 3 tries', 'metadata' => ['total' => 40, 'found' => 35, 'no_email_found' => 5], 'severity' => 'success'],
            ['brand_id' => $ujuzi->id, 'source' => 'hermes.ujuziplus.classifier', 'event_type' => 'reply_classified', 'title' => 'Reply: interested — Acme SACCO (flagged for follow-up)', 'metadata' => ['lead' => 'Acme SACCO', 'classification' => 'interested'], 'severity' => 'success'],
            ['brand_id' => $hudu->id, 'source' => 'laravel.scheduler.suppressions', 'event_type' => 'suppression_added', 'title' => '3 suppressions added — 2 hard bounces, 1 unsubscribe', 'metadata' => ['hard_bounce' => 2, 'unsubscribe' => 1], 'severity' => 'warning'],
            ['brand_id' => null, 'source' => 'laravel.control-tower.backup', 'event_type' => 'system', 'title' => 'Postgres backup completed — 4.2 GB', 'metadata' => ['size_mb' => 4200, 'duration_sec' => 187], 'severity' => 'info'],
            ['brand_id' => $ujuzi->id, 'source' => 'hermes.ujuziplus.mining', 'event_type' => 'mining_run', 'title' => '12 new leads mined — Tech startups Nairobi', 'metadata' => ['count' => 12, 'category' => 'startups', 'city' => 'nairobi'], 'severity' => 'success'],
            ['brand_id' => null, 'source' => 'laravel.control-tower.daily-brief', 'event_type' => 'daily_brief', 'title' => 'Daily brief — June 21', 'body' => "UjuziPlus:\n  • 23 leads mined (Nairobi SACCOs)\n  • 35/40 enriched\n  • 2 replies marked interested\n\nHudutech:\n  • Queue quiet\n  • No mining configured yet\n\nSystem:\n  • Postgres backup OK (4.2GB)\n  • Queue workers healthy", 'severity' => 'info'],
            ['brand_id' => $ujuzi->id, 'source' => 'laravel.scheduler.email-sender', 'event_type' => 'email_sent_batch', 'title' => '12 emails sent — UjuziPlus Rabbits, step 1', 'metadata' => ['count' => 12, 'brand' => 'ujuziplus', 'step' => 1], 'severity' => 'success'],
            ['brand_id' => $ujuzi->id, 'source' => 'laravel.scheduler.email-sender', 'event_type' => 'email_approved', 'title' => '8 emails approved via Telegram', 'metadata' => ['count' => 8, 'method' => 'telegram'], 'severity' => 'info'],
            ['brand_id' => null, 'source' => 'laravel.control-tower.queue', 'event_type' => 'system', 'title' => 'Queue worker restarted (3rd time today)', 'metadata' => ['count_today' => 3], 'severity' => 'warning'],
        ];

        foreach ($events as $data) {
            $logger->log($data);
        }

        $count = \App\Models\ActivityEvent::count();
        $this->info("Seeded {$count} activity events.");

        return self::SUCCESS;
    }
}
