<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandSequenceConfig extends Model
{
    use BelongsToBrand;

    protected $fillable = [
        'brand_id', 'segment', 'subcategory', 'source_condition', 'prompt_text', 'sequence_steps', 'is_active',
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
     * Resolve config for a brand+segment+subcategory with cascading fallback:
     *   1. Exact segment + requested subcategory (e.g. deer + hiring)
     *   2. Exact segment + 'general' (fallback for leads without a specific subcategory)
     *   3. 'all' segment fallback
     *
     * If no subcategory is provided, defaults to 'general'.
     */
    public static function resolveFor(int $brandId, string $segment, ?string $subcategory = null): ?self
    {
        $subcategory = $subcategory ?: 'general';

        return static::active()
            ->where('brand_id', $brandId)
            ->where(function ($q) use ($segment) {
                $q->where('segment', $segment)
                    ->orWhere('segment', 'all');
            })
            ->orderByRaw('CASE
                WHEN segment = ? AND subcategory = ? THEN 0
                WHEN segment = ? AND subcategory = \'general\' THEN 1
                WHEN segment = ? THEN 2
                ELSE 3
            END', [$segment, $subcategory, $segment, 'all'])
            ->first();
    }
}
