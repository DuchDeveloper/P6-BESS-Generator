<?php

declare(strict_types=1);

namespace App\Enums;

enum ResolutionType: string
{
    case Internal = 'internal';
    case External = 'external';
    case Unresolved = 'unresolved';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::External => 'External',
            self::Unresolved => 'Unresolved',
        };
    }
}