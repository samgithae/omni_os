<?php

namespace App\Console\Commands;

use App\Models\CronJobRun;
use Illuminate\Console\Command;

class CleanupCronJobRuns extends Command
{
    protected $signature = 'cron:cleanup-runs
                            {--older-than=30 : Mark running runs older than N minutes as failed}';

    protected $description = 'Clean up orphaned "running" cron job run records';

    public function handle(): int
    {
        $minutes = (int) $this->option('older-than');

        $count = CronJobRun::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes($minutes))
            ->update([
                'status' => 'failed',
                'exit_code' => -1,
                'output_summary' => 'Marked as failed by cleanup (stuck running > '.$minutes.' min)',
                'finished_at' => now(),
            ]);

        $this->info("Marked {$count} stuck running records as failed (older than {$minutes} min).");

        // Also report current counts
        $running = CronJobRun::where('status', 'running')->count();
        $success = CronJobRun::where('status', 'success')->count();
        $failed = CronJobRun::where('status', 'failed')->count();

        $this->info("Current: {$running} running, {$success} success, {$failed} failed.");

        return self::SUCCESS;
    }
}
