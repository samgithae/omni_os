<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Agent extends Model
{
    protected $fillable = [
        'codename',
        'display_name',
        'role',
        'description',
        'function_area',
        'avatar_path',
        'status',
        'is_active',
        'sort_order',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_active_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ActivityEvent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AgentDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        return null;
    }

    public function generateToken(): string
    {
        $plain = Str::random(48);

        $this->token_hash = hash('sha256', $plain);
        $this->token_last_four = substr($plain, -4);
        $this->save();

        return $plain;
    }

    public function touchActivity(): void
    {
        if ($this->last_active_at && $this->last_active_at->diffInSeconds(now()) < 60) {
            return;
        }

        $this->last_active_at = now();
        $this->saveQuietly();
    }

    public function actionsThisWeek(): int
    {
        return $this->activityEvents()
            ->where('created_at', '>=', now()->subWeek())
            ->count();
    }
}
