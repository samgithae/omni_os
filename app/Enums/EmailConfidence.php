<?php

namespace App\Enums;

use InvalidArgumentException;

enum EmailConfidence: string
{
    case Verified = 'verified';
    case Inferred = 'inferred';
    case Estimated = 'estimated';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::Inferred => 'Inferred',
            self::Estimated => 'Estimated',
            self::Unavailable => 'Unavailable',
        };
    }

    public function score(): int
    {
        return match ($this) {
            self::Verified => 100,
            self::Inferred => 75,
            self::Estimated => 40,
            self::Unavailable => 0,
        };
    }

    public function isDeliverable(): bool
    {
        return match ($this) {
            self::Verified, self::Inferred => true,
            default => false,
        };
    }

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Invalid email confidence [{$value}].");
    }
}
