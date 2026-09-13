<?php

declare(strict_types=1);

namespace App\Enums;

enum RelationshipType: string
{
    case FinishToStart = 'FS';
    case StartToStart = 'SS';
    case FinishToFinish = 'FF';
    case StartToFinish = 'SF';

    public function label(): string
    {
        return match ($this) {
            self::FinishToStart => 'Finish to Start',
            self::StartToStart => 'Start to Start',
            self::FinishToFinish => 'Finish to Finish',
            self::StartToFinish => 'Start to Finish',
        };
    }
}