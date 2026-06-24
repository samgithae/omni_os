<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\EmailMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $brand_id
 * @property int $lead_id
 * @property int $sequence_step
 * @property string|null $subject
 * @property string|null $body
 * @property string $status                  // draft, queued, sent, failed
 * @property string $approval_status         // pending, approved, rejected
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property string|null $approval_notes
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $clicked_at
 * @property string|null $error_message
 */
class EmailMessage extends Model
{
    use BelongsToBrand;

    /** @use HasFactory<EmailMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'lead_id',
        'sequence_step',
        'subject',
        'body',
        'status',
        'approval_status',
        'approved_at',
        'rejected_at',
        'approval_notes',
        'scheduled_for',
        'sent_at',
        'opened_at',
        'clicked_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sequence_step' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    // --- Scopes ---

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    public function scopeNeedsContent($query)
    {
        return $query->where('approval_status', 'needs_content');
    }

    // --- Approval helpers ---

    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isNeedsContent(): bool
    {
        return $this->approval_status === 'needs_content';
    }

    public function canBeApproved(): bool
    {
        return $this->subject !== null
            && $this->body !== null
            && $this->approval_status !== 'approved'
            && $this->approval_status !== 'needs_content';
    }

    public function markContentReady(): void
    {
        if ($this->approval_status === 'needs_content' && $this->subject && $this->body) {
            $this->update(['approval_status' => 'pending']);
        }
    }

    public function approve(?string $notes = null): void
    {
        $this->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approval_notes' => $notes,
            'status' => 'queued',
        ]);
    }

    public function reject(?string $notes = null): void
    {
        $this->update([
            'approval_status' => 'rejected',
            'rejected_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Get the status label with approval state for display.
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'sent') {
            return 'Sent';
        }
        if ($this->status === 'failed') {
            return 'Failed';
        }
        if ($this->status === 'queued') {
            return 'Queued';
        }
        if ($this->approval_status === 'rejected') {
            return 'Rejected';
        }
        if ($this->approval_status === 'approved') {
            return 'Approved';
        }
        return 'Pending Approval';
    }
}