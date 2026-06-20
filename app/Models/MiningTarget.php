<?php

namespace App\Models;

use Database\Factories\MiningTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $brand_id
 * @property string $country
 * @property string|null $city
 * @property string $category
 * @property string|null $search_template
 * @property string $segment
 * @property string $cadence
 * @property bool $is_active
 * @property Carbon|null $last_mined_at
 */
class MiningTarget extends Model
{
    /** @use HasFactory<MiningTargetFactory> */
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'country',
        'city',
        'category',
        'search_template',
        'segment',
        'cadence',
        'is_active',
        'last_mined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_mined_at' => 'datetime',
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

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    public function scopeBySegment($query, string $segment)
    {
        return $query->where('segment', $segment);
    }
}
