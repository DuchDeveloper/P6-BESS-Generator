<?php

declare(strict_types=1);

namespace App\Enums;

enum OwnershipMode: string
{
    case Internal = 'internal';
    case External = 'external';
    case IncludedElsewhere = 'included_elsewhere';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::External => 'External',
            self::IncludedElsewhere => 'Included Elsewhere',
            self::NotApplicable => 'Not Applicable',
        };
    }

    public function generatesActivities(): bool
    {
        return $this === self::Internal;
    }

    public function generatesBoundaryMilestone(): bool
    {
        return $this === self::External;
    }
}