<?php

namespace App\Listeners;

use App\Models\CronJobRun;
use App\Services\ActivityLogger;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;

class TrackCronJobRuns
{
    /** @var array<string, int[]> Stack of run IDs per job (supports overlapping runs) */
    private static array $running = [];

    public function handle(object $event): void
    {
        if ($event instanceof ScheduledTaskStarting) {
            $this->handleStarting($event);
        } elseif ($event instanceof ScheduledTaskFinished) {
            $this->handleFinished($event);
        }
    }

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $task = $event->task;
        $jobName = $this->resolveJobName($task);

        $run = CronJobRun::create([
            'job_name' => $jobName,
            'command' => $task->command ?? $task->description,
            'description' => $task->description ?? $jobName,
            'schedule' => $task->expression,
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Use array so overlapping runs don't overwrite each other
        self::$running[$jobName][] = $run->id;
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $task = $event->task;
        $jobName = $this->resolveJobName($task);

        $runIds = self::$running[$jobName] ?? [];
        if (empty($runIds)) {
            return;
        }

        // Pop the oldest run for this job (FIFO)
        $runId = array_shift($runIds);
        self::$running[$jobName] = $runIds;

        $run = CronJobRun::find($runId);
        if (!$run) {
            return;
        }

        $success = $event->task->exitCode === 0;
        $durationMs = (int) ($event->elapsed * 1000);

        $run->update([
            'status' => $success ? 'success' : 'failed',
            'exit_code' => $event->task->exitCode,
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ]);

        unset(self::$running[$jobName]);

        // Report to Activity Feed in human-readable format
        $this->logToActivityFeed($run, $success, $durationMs, $task->description ?? $jobName);
    }

    private function logToActivityFeed(CronJobRun $run, bool $success, int $durationMs, string $description): void
    {
        $duration = $durationMs < 1000
            ? $durationMs . 'ms'
            : number_format($durationMs / 1000, 1) . 's';

        $emoji = $success ? '✅' : '❌';
        $title = "{$emoji} {$run->job_name} — " . ($success ? 'completed' : 'failed');

        if ($description) {
            $title .= ': ' . $description;
        }

        $body = $success
            ? "Ran successfully in {$duration}."
            : "Failed with exit code {$run->exit_code} after {$duration}.";

        app(ActivityLogger::class)->log([
            'brand_id' => null,
            'source' => 'scheduler.' . $run->job_name,
            'event_type' => $success ? 'system' : 'system',
            'title' => $title,
            'body' => $body,
            'metadata' => [
                'job_name' => $run->job_name,
                'command' => $run->command,
                'status' => $run->status,
                'exit_code' => $run->exit_code,
                'duration_ms' => $durationMs,
                'started_at' => $run->started_at?->toIso8601String(),
                'description' => $description,
            ],
            'severity' => $success ? 'info' : 'error',
        ]);
    }

    private function resolveJobName($task): string
    {
        if ($task->command) {
            $parts = explode(' ', $task->command);
            foreach ($parts as $part) {
                if (str_contains($part, 'artisan')) continue;
                if (str_starts_with($part, '/')) continue;
                if (str_contains($part, 'php')) continue;
                return $part;
            }
            return $parts[count($parts) - 1] ?? 'unknown';
        }

        if ($task->description) {
            return str_replace(' ', '_', strtolower(substr($task->description, 0, 50)));
        }

        return 'unknown';
    }
}