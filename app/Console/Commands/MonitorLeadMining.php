<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadEvent;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class MonitorLeadMining extends Command
{
    protected $signature = 'leads:monitor-mining
        {--hours=2 : Look back window in hours}
        {--dry-run : Preview without logging}';

    protected $description = 'Monitor lead mining pipeline health and log status. Actual mining is done by Hermes crons (ujuziplus-lead-mining skill, every 2h). This command tracks the pipeline on the jobs dashboard.';

    public function handle(ActivityLogger $logger): int
    {
        $hours = (int) $this->option('hours');
        $since = now()->subHours($hours);

        // Count leads created in the lookback window
        $newLeads = Lead::where('created_at', '>=', $since)->count();
        $newEnriched = Lead::where('created_at', '>=', $since)->where('status', 'enriched')->count();

        // Count mining events in the lookback window
        $miningEvents = LeadEvent::where('event_type', 'imported')
            ->where('created_at', '>=', $since)
            ->count();

        // Total leads mined to date
        $totalLeads = Lead::count();

        $this->info('=== Lead Mining Pipeline Status ===');
        $this->line("Lookback window:    {$hours} hours");
        $this->line("New leads found:    {$newLeads}");
        $this->line("New enriched:       {$newEnriched}");
        $this->line("Mining events:      {$miningEvents}");
        $this->line("Total leads:        {$totalLeads}");

        // Determine health
        $healthy = $newLeads > 0;
        $status = $healthy ? 'success' : 'warning';
        $severity = $healthy ? 'success' : 'warning';

        $body = "Monitored lead mining pipeline over the last {$hours}h. "
            . "{$newLeads} new leads added ({$newEnriched} enriched). "
            . "Total leads database: {$totalLeads}. "
            . "Hermes crons (Rabbit + Deer, every 2h) handle the actual mining.";

        if ($newLeads === 0) {
            $body .= " No leads mined in the last {$hours}h — check Hermes cron logs.";
        }

        if (! $this->option('dry-run')) {
            $logger->log(
                eventType: 'system',
                title: $healthy
                    ? "Lead mining: {$newLeads} new leads in last {$hours}h"
                    : "Lead mining: no activity in last {$hours}h",
                body: $body,
                severity: $severity,
                source: 'hermes:leads:monitor-mining',
            );

            $this->line('');
            $this->info('Activity Feed event logged.');
        }

        return $healthy ? 0 : 1;
    }
}
