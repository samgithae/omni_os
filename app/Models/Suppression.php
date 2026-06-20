<?php

namespace App\Models;

use Database\Factories\SuppressionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $brand_id
 * @property string $email
 * @property string $reason
 * @property string|null $notes
 */
class Suppression extends Model
{
    /** @use HasFactory<SuppressionFactory> */
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'email',
        'reason',
        'notes',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeUnsubscribes($query)
    {
        return $query->where('reason', 'unsubscribe');
    }

    public function scopeHardBounces($query)
    {
        return $query->where('reason', 'hard_bounce');
    }
}
