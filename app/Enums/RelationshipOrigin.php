<?php

declare(strict_types=1);

namespace App\Enums;

enum RelationshipOrigin: string
{
    case Internal = 'internal';
    case Dependency = 'dependency';
    case Interface = 'interface';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal (within package)',
            self::Dependency => 'Dependency (cross-package)',
            self::Interface => 'Interface (equipment)',
            self::Manual => 'Manual',
        };
    }
}