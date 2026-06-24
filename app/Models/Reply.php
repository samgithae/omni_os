<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $brand_id
 * @property int|null $email_message_id
 * @property string $from_email
 * @property string|null $subject
 * @property string $body
 * @property string|null $body_html
 * @property string|null $classification
 * @property string|null $classification_confidence
 * @property string|null $classification_summary
 * @property string $direction
 * @property bool $read
 * @property string|null $received_at
 */
class Reply extends Model
{
    use BelongsToBrand;

    protected $fillable = [
        'lead_id',
        'brand_id',
        'email_message_id',
        'from_email',
        'subject',
        'body',
        'body_html',
        'classification',
        'classification_confidence',
        'classification_summary',
        'direction',
        'read',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'read' => 'boolean',
            'received_at' => 'datetime',
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

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    // --- Scopes ---

    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeByClassification($query, string $classification)
    {
        return $query->where('classification', $classification);
    }

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeForLead($query, int $leadId)
    {
        return $query->where('lead_id', $leadId);
    }
}