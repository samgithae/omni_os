<?php

namespace App\Models;

use App\Enums\ActivityEventType;
use App\Enums\ActivitySeverity;
use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEvent extends Model
{
    use BelongsToBrand;

    protected $fillable = [
        'brand_id',
        'source',
        'event_type',
        'title',
        'body',
        'metadata',
        'severity',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'severity' => ActivitySeverity::class,
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActivityEventComment::class)->orderBy('created_at');
    }

    public function scopeForBrand(Builder $query, ?int $brandId): Builder
    {
        if ($brandId === null) {
            return $query;
        }

        return $query->where('brand_id', $brandId);
    }

    public function scopeSeverity(Builder $query, string $level): Builder
    {
        return $query->where('severity', $level);
    }

    public function scopeRecent(Builder $query, int $limit = 30): Builder
    {
        return $query->latest()->limit($limit);
    }

    public function scopeSince(Builder $query, int $sinceId): Builder
    {
        return $query->where('id', '>', $sinceId);
    }
}
