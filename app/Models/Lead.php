<?php

namespace App\Models;

use Database\Factories\LeadFactory;
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
        return $query->where('status', $status);
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
        return $query->where('status', 'enriched');
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    // --- Status helpers ---

    public function isSuppressed(): bool
    {
        if (! $this->email) {
            return false;
        }

        return Suppression::where('brand_id', $this->brand_id)
            ->where('email', $this->email)
            ->exists();
    }
}
