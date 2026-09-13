<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidationSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::Warning => 'Warning',
            self::Info => 'Info',
        };
    }

    public function blocksExport(): bool
    {
        return $this === self::Critical;
    }
}