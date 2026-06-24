<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandSequenceConfig extends Model
{
    use BelongsToBrand;

    protected $fillable = [
        'brand_id', 'segment', 'prompt_text', 'sequence_steps', 'is_active',
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
     * Resolve config for a brand+segment with fallback to 'all'.
     * Returns the segment-specific config if one exists, otherwise the 'all' fallback.
     */
    public static function resolveFor(int $brandId, string $segment): ?self
    {
        return static::active()
            ->where('brand_id', $brandId)
            ->where(function ($q) use ($segment) {
                $q->where('segment', $segment)->orWhere('segment', 'all');
            })
            ->orderByRaw("CASE WHEN segment = ? THEN 0 ELSE 1 END", [$segment])
            ->first();
    }
}
