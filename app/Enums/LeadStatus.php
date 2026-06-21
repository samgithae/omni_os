<?php

namespace App\Enums;

use InvalidArgumentException;

enum LeadStatus: string
{
    case New = 'new';
    case Enriching = 'enriching';
    case Enriched = 'enriched';
    case Emailed = 'emailed';
    case Replied = 'replied';
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case NoEmailFound = 'no_email_found';
    case Suppressed = 'suppressed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Enriching => 'Enriching',
            self::Enriched => 'Enriched',
            self::Emailed => 'Emailed',
            self::Replied => 'Replied',
            self::Interested => 'Interested',
            self::NotInterested => 'Not Interested',
            self::NoEmailFound => 'No Email Found',
            self::Suppressed => 'Suppressed',
            self::Closed => 'Closed',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Enriching => 'warning',
            self::Enriched, self::Interested => 'success',
            self::Emailed, self::Replied => 'primary',
            self::NoEmailFound, self::NotInterested, self::Suppressed, self::Closed => 'danger',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::New => '#3b82f6',
            self::Enriching => '#f59e0b',
            self::Enriched => '#10b981',
            self::Emailed => '#6366f1',
            self::Replied => '#8b5cf6',
            self::Interested => '#059669',
            self::NotInterested => '#dc2626',
            self::NoEmailFound => '#ef4444',
            self::Suppressed => '#7f1d1d',
            self::Closed => '#6b7280',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::NoEmailFound, self::NotInterested, self::Suppressed, self::Closed => true,
            default => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Enriching, self::Suppressed],
            self::Enriching => [self::Enriched, self::NoEmailFound, self::Suppressed],
            self::Enriched => [self::Emailed, self::Suppressed],
            self::Emailed => [self::Replied, self::Suppressed, self::Closed],
            self::Replied => [self::Interested, self::NotInterested, self::Suppressed, self::Closed],
            self::Interested => [self::Closed, self::Suppressed],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Invalid lead status [{$value}].");
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
