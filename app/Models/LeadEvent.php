<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\LeadEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $brand_id
 * @property string $event_type
 * @property array|null $payload
 * @property string|null $source
 */
class LeadEvent extends Model
{
    use BelongsToBrand;

    /** @use HasFactory<LeadEventFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'brand_id',
        'event_type',
        'payload',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
