<?php

namespace App\Enums;

enum CommentAuthor: string
{
    case Human = 'human';
    case Hermes = 'hermes';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Human => 'Sam',
            self::Hermes => 'Hermes',
            self::Agent => 'Agent',
        };
    }

    public function isHuman(): bool
    {
        return $this === self::Human;
    }
}
