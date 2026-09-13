<?php

declare(strict_types=1);

namespace App\Enums;

enum RmuTopology: string
{
    case None = 'none';
    case PerSut = 'per_sut';
    case PerBlock = 'per_block';
    case PerZone = 'per_zone';
    case PerSutAndBlock = 'per_sut_and_block';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No RMU',
            self::PerSut => 'RMU per SUT',
            self::PerBlock => 'RMU per Block',
            self::PerZone => 'RMU per Zone',
            self::PerSutAndBlock => 'RMU per SUT and per Block (Two-Level)',
        };
    }

    public function generatesRmu(): bool
    {
        return $this !== self::None;
    }
}