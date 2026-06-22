<?php

namespace App\Http\Controllers;

use App\Models\CronJobRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobsController extends Controller
{
    public function index(Request $request)
    {
        // Define all known scheduled jobs with their details
        $jobDefinitions = [
            [
                'name' => 'queue:prune-failed',
                'command' => 'queue:prune-failed --hours=336',
                'description' => 'Clean up old failed queue jobs (14 day retention)',
                'schedule' => '0 2:30 * * *',
                'schedule_label' => 'Daily at 2:30 AM',
                'group' => 'system',
            ],
            [
                'name' => 'emails:send-batch',
                'command' => 'emails:send-batch --limit=20',
                'description' => 'Send approved/queued emails via SMTP2GO with safe-send (business hours only)',
                'schedule' => '*/15 * * * *',
                'schedule_label' => 'Every 15 minutes (8AM-6PM EAT)',
                'group' => 'email',
            ],
            [
                'name' => 'emails:notify-telegram',
                'command' => 'emails:notify-telegram --limit=15',
                'description' => 'Send pending email approval requests to Telegram with content preview',
                'schedule' => '*/30 * * * *',
                'schedule_label' => 'Every 30 minutes',
                'group' => 'email',
            ],
            [
                'name' => 'ProcessSequenceProgressions',
                'command' => 'ProcessSequenceProgressions (job)',
                'description' => 'Progress email sequences: schedule next steps for leads (skips weekends)',
                'schedule' => '0 5:00 * * *',
                'schedule_label' => 'Daily at 5 AM (weekdays only)',
                'group' => 'email',
            ],
            [
                'name' => 'telegram:poll-approvals',
                'command' => 'telegram:poll-approvals',
                'description' => 'Poll Telegram for approval replies (text commands + inline callbacks)',
                'schedule' => '* * * * *',
                'schedule_label' => 'Every minute',
                'group' => 'messaging',
            ],
            [
                'name' => 'activity:daily-brief',
                'command' => 'activity:daily-brief',
                'description' => 'Generate daily system overview brief and post to Activity Feed',
                'schedule' => '0 7:00 * * *',
                'schedule_label' => 'Daily at 7 AM',
                'group' => 'system',
            ],
            [
                'name' => 'leads:score',
                'command' => 'leads:score',
                'description' => 'Recalculate lead scores (segment, completeness, engagement, email confidence)',
                'schedule' => '0 3:00 * * *',
                'schedule_label' => 'Daily at 3 AM',
                'group' => 'leads',
            ],
            [
                'name' => 'winloss:generate',
                'command' => 'winloss:generate',
                'description' => 'Generate win-loss report from reply outcomes and pipeline metrics',
                'schedule' => '0 6:00 * * 1',
                'schedule_label' => 'Weekly on Monday at 6 AM',
                'group' => 'analytics',
            ],
            [
                'name' => 'inbox:poll',
                'command' => 'inbox:poll --days=3 --limit=30',
                'description' => 'Poll IMAP inbox for incoming replies and create Reply records',
                'schedule' => '*/10 * * * *',
                'schedule_label' => 'Every 10 minutes',
                'group' => 'messaging',
            ],
        ];

        // Build latest run data for each job
        $jobData = [];
        foreach ($jobDefinitions as $def) {
            $lastRun = CronJobRun::forJob($def['name'])->latest('started_at')->first();
            $runCount = CronJobRun::forJob($def['name'])->count();

            // Last 24 hours status
            $runs24h = CronJobRun::forJob($def['name'])
                ->since(now()->subDay())
                ->get();

            $success24h = $runs24h->where('status', 'success')->count();
            $failed24h = $runs24h->where('status', 'failed')->count();
            $running24h = $runs24h->where('status', 'running')->count();

            $jobData[] = [
                'name' => $def['name'],
                'command' => $def['command'],
                'description' => $def['description'],
                'schedule' => $def['schedule'],
                'schedule_label' => $def['schedule_label'],
                'group' => $def['group'],
                'last_run' => $lastRun ? [
                    'status' => $lastRun->status,
                    'exit_code' => $lastRun->exit_code,
                    'duration_ms' => $lastRun->duration_ms,
                    'started_at' => $lastRun->started_at?->toIso8601String(),
                    'finished_at' => $lastRun->finished_at?->toIso8601String(),
                    'output_summary' => $lastRun->output_summary,
                ] : null,
                'stats' => [
                    'total_runs' => $runCount,
                    'runs_24h' => $runs24h->count(),
                    'success_24h' => $success24h,
                    'failed_24h' => $failed24h,
                    'running_24h' => $running24h,
                ],
            ];
        }

        // Compute overall stats
        $totalRunsAll = CronJobRun::count();
        $successAll = CronJobRun::successful()->count();
        $failedAll = CronJobRun::failed()->count();
        $overallHealth = $totalRunsAll > 0
            ? round(($successAll / $totalRunsAll) * 100, 1)
            : 100;

        return Inertia::render('Analytics/Jobs', [
            'jobs' => $jobData,
            'stats' => [
                'total_jobs' => count($jobDefinitions),
                'total_runs' => $totalRunsAll,
                'success_count' => $successAll,
                'failed_count' => $failedAll,
                'overall_health' => $overallHealth,
            ],
        ]);
    }

    /**
     * API endpoint for run history with date filtering.
     */
    public function history(Request $request)
    {
        $request->validate([
            'job' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = CronJobRun::query();

        if ($request->filled('job')) {
            $query->forJob($request->job);
        }

        if ($request->filled('from')) {
            $query->since($request->from);
        }

        if ($request->filled('to')) {
            $query->until($request->to . ' 23:59:59');
        }

        $limit = (int) ($request->get('limit', 100));

        $runs = $query->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(function ($run) {
                return [
                    'id' => $run->id,
                    'job_name' => $run->job_name,
                    'status' => $run->status,
                    'exit_code' => $run->exit_code,
                    'duration_ms' => $run->duration_ms,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'finished_at' => $run->finished_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'runs' => $runs,
            'total' => $query->count(),
        ]);
    }
}