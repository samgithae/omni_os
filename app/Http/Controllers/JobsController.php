<?php

namespace App\Http\Controllers;

use App\Models\CronJobRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobsController extends Controller
{
    public function index(Request $request)
    {
        // Read job definitions from config
        $jobDefinitions = config('schedule-jobs.jobs', []);

        // Build latest run data for each job
        $jobData = [];
        foreach ($jobDefinitions as $def) {
            $lastRun = CronJobRun::forJob($def['name'])->latest('started_at')->first();
            $runCount = CronJobRun::forJob($def['name'])->count();

            $runs24h = CronJobRun::forJob($def['name'])
                ->since(now()->subDay())
                ->get();

            $success24h = $runs24h->where('status', 'success')->count();
            $failed24h = $runs24h->where('status', 'failed')->count();
            $running24h = $runs24h->where('status', 'running')->count();

            $jobData[] = [
                'name' => $def['name'],
                'command' => $def['command'],
                'description' => $def['description'] ?? '',
                'schedule' => $def['schedule'] ?? '',
                'schedule_label' => $def['schedule_label'] ?? $def['schedule'] ?? '',
                'group' => $def['group'] ?? 'other',
                'last_run' => $lastRun ? [
                    'status' => $lastRun->status,
                    'exit_code' => $lastRun->exit_code,
                    'duration_ms' => $lastRun->duration_ms,
                    'started_at' => $lastRun->started_at?->toIso8601String(),
                    'finished_at' => $lastRun->finished_at?->toIso8601String(),
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

        // Overall stats
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
            ->map(fn ($run) => [
                'id' => $run->id,
                'job_name' => $run->job_name,
                'status' => $run->status,
                'exit_code' => $run->exit_code,
                'duration_ms' => $run->duration_ms,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ]);

        return response()->json([
            'runs' => $runs,
            'total' => $query->count(),
        ]);
    }
}