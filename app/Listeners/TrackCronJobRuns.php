<?php

namespace App\Listeners;

use App\Models\CronJobRun;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Log;

class TrackCronJobRuns
{
    private static array $running = [];

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof ScheduledTaskStarting) {
            $this->handleStarting($event);
        } elseif ($event instanceof ScheduledTaskFinished) {
            $this->handleFinished($event);
        }
    }

    public function subscribe(\Illuminate\Events\Dispatcher $events): void
    {
        $events->listen(
            ScheduledTaskStarting::class,
            [self::class, 'handleStarting']
        );

        $events->listen(
            ScheduledTaskFinished::class,
            [self::class, 'handleFinished']
        );
    }

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $task = $event->task;

        $jobName = $this->resolveJobName($task);

        $run = CronJobRun::create([
            'job_name' => $jobName,
            'command' => $task->command ?? $task->description,
            'description' => $this->resolveDescription($task, $jobName),
            'schedule' => $task->expression,
            'status' => 'running',
            'started_at' => now(),
        ]);

        self::$running[$jobName] = $run->id;
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $task = $event->task;
        $jobName = $this->resolveJobName($task);

        $runId = self::$running[$jobName] ?? null;
        if (!$runId) {
            return;
        }

        $run = CronJobRun::find($runId);
        if (!$run) {
            return;
        }

        $success = $event->task->exitCode === 0;

        $run->update([
            'status' => $success ? 'success' : 'failed',
            'exit_code' => $event->task->exitCode,
            'duration_ms' => (int) ($event->elapsed * 1000),
            'finished_at' => now(),
        ]);

        unset(self::$running[$jobName]);
    }

    private function resolveJobName($task): string
    {
        if ($task->command) {
            // Extract the command name from the full command string
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

    private function resolveDescription($task, string $jobName): string
    {
        // Known job descriptions
        $descriptions = [
            'queue:prune-failed' => 'Clean up old failed queue jobs',
            'emails:send-batch' => 'Send approved/queued emails via SMTP2GO with safe-send discipline',
            'emails:notify-telegram' => 'Send pending email approval requests to Telegram',
            'ProcessSequenceProgressions' => 'Progress email sequences: schedule next steps for leads',
            'telegram:poll-approvals' => 'Poll Telegram for approval replies and process them',
            'activity:daily-brief' => 'Generate daily brief and post to Activity Feed',
            'leads:score' => 'Recalculate lead scores based on segment, data completeness, email confidence, and engagement',
            'winloss:generate' => 'Generate win-loss report from reply outcomes and pipeline metrics',
            'inbox:poll' => 'Poll IMAP inbox for incoming replies and create Reply records',
        ];

        return $descriptions[$jobName]
            ?? $task->description
            ?? $task->command
            ?? $jobName;
    }
}