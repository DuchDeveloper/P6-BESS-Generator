<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Configured = 'configured';
    case Compiled = 'compiled';
    case Validated = 'validated';
    case Exported = 'exported';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Configured => 'Configured',
            self::Compiled => 'Compiled',
            self::Validated => 'Validated',
            self::Exported => 'Exported',
        };
    }
}