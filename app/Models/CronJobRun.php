<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $job_name
 * @property string|null $command
 * @property string|null $description
 * @property string|null $schedule
 * @property string $status
 * @property int|null $exit_code
 * @property int|null $duration_ms
 * @property string|null $output_summary
 * @property string $started_at
 * @property string|null $finished_at
 */
class CronJobRun extends Model
{
    protected $fillable = [
        'job_name',
        'command',
        'description',
        'schedule',
        'status',
        'exit_code',
        'duration_ms',
        'output_summary',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function scopeForJob($query, string $jobName)
    {
        return $query->where('job_name', $jobName);
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->latest('started_at')->limit($limit);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSince($query, $date)
    {
        return $query->where('started_at', '>=', $date);
    }

    public function scopeUntil($query, $date)
    {
        return $query->where('started_at', '<=', $date);
    }
}
