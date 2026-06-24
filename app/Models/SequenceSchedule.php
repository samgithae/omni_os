<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceSchedule extends Model
{
    use BelongsToBrand;

    protected $fillable = [
        'brand_id',
        'segment',
        'step',
        'days_after_previous',
        'purpose',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'days_after_previous' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSegment($query, string $segment)
    {
        return $query->where('segment', $segment);
    }
}
