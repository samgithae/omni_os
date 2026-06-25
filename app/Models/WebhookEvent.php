<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $source
 * @property string $event_type
 * @property string|null $recipient_email
 * @property string|null $smtp2go_id
 * @property int|null $email_message_id
 * @property int|null $lead_id
 * @property array $payload
 * @property bool $processed
 * @property string|null $processing_notes
 * @property string $received_at
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'source',
        'event_type',
        'recipient_email',
        'smtp2go_id',
        'email_message_id',
        'lead_id',
        'payload',
        'processed',
        'processing_notes',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    public function scopeByEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }
}
