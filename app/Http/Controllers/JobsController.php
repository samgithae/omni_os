<?php

namespace App\Http\Controllers;

use App\Models\CronJobRun;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

        // Overall stats — only count completed runs (exclude 'running')
        $completedRuns = CronJobRun::whereIn('status', ['success', 'failed'])->count();
        $totalRunsAll = CronJobRun::count();
        $successAll = CronJobRun::successful()->count();
        $failedAll = CronJobRun::failed()->count();
        $runningCount = CronJobRun::where('status', 'running')->count();
        $overallHealth = $completedRuns > 0
            ? round(($successAll / $completedRuns) * 100, 1)
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
     * POST /analytics/jobs/{jobName}/run
     * Trigger a manual run of a scheduled job.
     */
    public function run(string $jobName, ActivityLogger $logger): \Illuminate\Http\JsonResponse
    {
        $jobDefinitions = config('schedule-jobs.jobs', []);
        $definition = collect($jobDefinitions)->firstWhere('name', $jobName);

        if (! $definition) {
            return response()->json(['error' => "Job '{$jobName}' not found."], 404);
        }

        $command = $definition['command'] ?? '';

        if (str_ends_with($command, ' (job)')) {
            // Queue jobs can't be run via Artisan::call
            return response()->json(['error' => 'Queue jobs cannot be run manually from this panel.'], 422);
        }

        $startedAt = now();

        try {
            $exitCode = Artisan::call($command);
            $output = Artisan::output();
            $duration = (int) round(abs(now()->diffInMilliseconds($startedAt)));

            // Log the run
            CronJobRun::create([
                'job_name' => $jobName,
                'status' => $exitCode === 0 ? 'success' : 'failed',
                'exit_code' => $exitCode,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'duration_ms' => $duration,
                'output_summary' => substr($output, 0, 500),
            ]);

            $logger->log([
                'brand_id' => null,
                'source' => 'system:manual_run',
                'event_type' => 'system',
                'title' => "Manual run: {$jobName}",
                'body' => "Manual run triggered from jobs dashboard. Exit code: {$exitCode}. Duration: {$duration}ms.",
                'severity' => $exitCode === 0 ? 'success' : 'warning',
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'duration_ms' => $duration,
                'output' => substr($output, 0, 1000),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
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