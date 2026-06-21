<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $brand_id
 * @property string|null $company_name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string $segment
 * @property string|null $category
 * @property string|null $subcategory
 * @property string $country
 * @property string|null $city
 * @property string|null $address
 * @property string $status
 * @property int $enrichment_attempts
 * @property bool $email_verified
 * @property int $score
 * @property string|null $source
 * @property string|null $source_url
 * @property array|null $raw_data
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected ?array $pendingStatusTransition = null;

    protected ?string $statusTransitionSource = null;

    protected array $statusTransitionContext = [];

    protected $fillable = [
        'brand_id',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'website',
        'segment',
        'category',
        'subcategory',
        'country',
        'city',
        'address',
        'status',
        'enrichment_attempts',
        'email_verified',
        'score',
        'source',
        'source_url',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'email_verified' => 'boolean',
            'enrichment_attempts' => 'integer',
            'score' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            $lead->status ??= LeadStatus::New->value;

            LeadStatus::fromValue($lead->status);
        });

        static::saving(function (Lead $lead): void {
            if (! $lead->exists || ! $lead->isDirty('status')) {
                return;
            }

            $lead->prepareStatusTransition(
                LeadStatus::fromValue((string) $lead->getOriginal('status')),
                LeadStatus::fromValue((string) $lead->status),
            );
        });

        static::saved(function (Lead $lead): void {
            if (! $lead->pendingStatusTransition) {
                return;
            }

            LeadEvent::create([
                'lead_id' => $lead->id,
                'brand_id' => $lead->brand_id,
                'event_type' => 'status_changed',
                'payload' => [
                    'from' => $lead->pendingStatusTransition['from']->value,
                    'to' => $lead->pendingStatusTransition['to']->value,
                    ...$lead->statusTransitionContext,
                ],
                'source' => $lead->statusTransitionSource ?? 'lead_model',
            ]);

            $lead->pendingStatusTransition = null;
            $lead->statusTransitionSource = null;
            $lead->statusTransitionContext = [];
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    // --- Scopes ---

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeBySegment($query, string $segment)
    {
        return $query->where('segment', $segment);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', LeadStatus::fromValue($status)->value);
    }

    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    public function scopeByCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeRabbits($query)
    {
        return $query->where('segment', 'rabbit');
    }

    public function scopeDeer($query)
    {
        return $query->where('segment', 'deer');
    }

    public function scopeEnriched($query)
    {
        return $query->where('status', LeadStatus::Enriched->value);
    }

    public function scopeNew($query)
    {
        return $query->where('status', LeadStatus::New->value);
    }

    // --- Status helpers ---

    public static function statusOptions(): array
    {
        return LeadStatus::options();
    }

    public function statusEnum(): LeadStatus
    {
        return LeadStatus::fromValue($this->status);
    }

    public function transitionTo(LeadStatus|string $target, ?string $source = null, array $context = []): void
    {
        $targetStatus = $target instanceof LeadStatus ? $target : LeadStatus::fromValue($target);

        $this->statusTransitionSource = $source ?? 'lead.transition';
        $this->statusTransitionContext = $context;
        $this->status = $targetStatus->value;
        $this->save();
    }

    public function isSuppressed(): bool
    {
        if (! $this->email) {
            return false;
        }

        return Suppression::query()
            ->where('brand_id', $this->brand_id)
            ->where('email', $this->email)
            ->exists();
    }

    protected function prepareStatusTransition(LeadStatus $from, LeadStatus $to): void
    {
        if ($from === $to) {
            throw new DomainException("Lead is already in status [{$to->value}].");
        }

        if (! $from->canTransitionTo($to)) {
            throw new DomainException("Invalid lead status transition [{$from->value} -> {$to->value}].");
        }

        $this->pendingStatusTransition = [
            'from' => $from,
            'to' => $to,
        ];
    }
}
