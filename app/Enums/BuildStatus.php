<?php

declare(strict_types=1);

namespace App\Enums;

enum BuildStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Waiting to start',
            self::Running => 'Building',
            self::Succeeded => 'Built',
            self::Failed => 'Failed',
        };
    }

    public function inProgress(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
