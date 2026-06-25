<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $primary_market
 * @property string|null $primary_kpi
 * @property string|null $brand_voice
 * @property string|null $color
 * @property bool $is_active
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'primary_market',
        'primary_kpi',
        'brand_voice',
        'color',
        'is_active',
        'sender_emails',
        'sender_name',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sender_emails' => 'array',
            'settings' => 'array',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function suppressions(): HasMany
    {
        return $this->hasMany(Suppression::class);
    }

    public function miningTargets(): HasMany
    {
        return $this->hasMany(MiningTarget::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get a random sender email from this brand's sender pool.
     * Falls back to config('mail.from.address') if none configured.
     */
    public function randomSenderEmail(): string
    {
        $emails = $this->sender_emails ?? [];
        if (empty($emails)) {
            return config('mail.from.address');
        }

        return $emails[array_rand($emails)];
    }
}
