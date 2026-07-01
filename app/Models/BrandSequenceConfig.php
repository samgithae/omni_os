<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandSequenceConfig extends Model
{
    use BelongsToBrand;

    protected $fillable = [
        'brand_id', 'segment', 'source_condition', 'prompt_text', 'sequence_steps', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sequence_steps' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // --- Scopes ---

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForBrand($query, int $brandId): void
    {
        $query->where('brand_id', $brandId);
    }

    /**
     * Resolve config for a brand+segment+source with cascading fallback:
     *   1. Exact segment + source_condition LIKE match
     *   2. Exact segment + null source_condition (generic segment config)
     *   3. 'all' segment fallback
     */
    public static function resolveFor(int $brandId, string $segment, ?string $source = null): ?self
    {
        return static::active()
            ->where('brand_id', $brandId)
            ->where(function ($q) use ($segment, $source) {
                $q->where('segment', $segment)
                    ->orWhere('segment', 'all');
            })
            ->when($source, function ($q, $source) use ($segment) {
                // When a source is provided, matching source_condition wins over null
                $q->orderByRaw('CASE
                    WHEN segment = ? AND source_condition IS NOT NULL AND ? LIKE source_condition THEN 0
                    WHEN segment = ? AND source_condition IS NULL THEN 1
                    WHEN segment = ? THEN 2
                    ELSE 3
                END', [$segment, $source, $segment, 'all']);
            }, function ($q) use ($segment) {
                // No source provided: existing behaviour — segment-specific wins, then 'all'
                $q->orderByRaw('CASE WHEN segment = ? THEN 0 ELSE 1 END', [$segment]);
            })
            ->first();
    }
}
