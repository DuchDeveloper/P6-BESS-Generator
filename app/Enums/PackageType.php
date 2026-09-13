<?php

declare(strict_types=1);

namespace App\Enums;

enum PackageType: string
{
    case Design = 'design';
    case Procurement = 'procurement';
    case Construction = 'construction';
    case Commissioning = 'commissioning';
    case Interface = 'interface';
    case Management = 'management';
    case Milestone = 'milestone';

    public function label(): string
    {
        return match ($this) {
            self::Design => 'Design',
            self::Procurement => 'Procurement',
            self::Construction => 'Construction',
            self::Commissioning => 'Commissioning',
            self::Interface => 'Interface',
            self::Management => 'Management',
            self::Milestone => 'Milestone',
        };
    }
}