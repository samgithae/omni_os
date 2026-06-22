<?php

namespace App\Models;

use App\Enums\CommentAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $activity_event_id
 * @property string $author
 * @property string $body
 * @property array|null $metadata
 * @property bool $is_instruction
 * @property string|null $instruction_status
 * @property string|null $acknowledged_at
 */
class ActivityEventComment extends Model
{
    protected $fillable = [
        'activity_event_id',
        'author',
        'body',
        'metadata',
        'is_instruction',
        'instruction_status',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_instruction' => 'boolean',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ActivityEvent::class, 'activity_event_id');
    }

    public function scopeInstructions($query)
    {
        return $query->where('is_instruction', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_instruction', true)
            ->where('instruction_status', 'pending');
    }
}