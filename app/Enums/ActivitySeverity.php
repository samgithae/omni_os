<?php

namespace App\Enums;

use InvalidArgumentException;

enum ActivitySeverity: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Success => 'Success',
            self::Warning => 'Warning',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => '#6b7280',
            self::Success => '#10b981',
            self::Warning => '#f59e0b',
            self::Error => '#ef4444',
        };
    }

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Invalid activity severity [{$value}].");
    }
}
