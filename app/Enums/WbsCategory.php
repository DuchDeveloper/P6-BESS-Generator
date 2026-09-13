<?php

declare(strict_types=1);

namespace App\Enums;

enum WbsCategory: string
{
    case Milestones = 'milestones';
    case Management = 'management';
    case Design = 'design';
    case Procurement = 'procurement';
    case Construction = 'construction';
    case Commissioning = 'commissioning';

    public function label(): string
    {
        return match ($this) {
            self::Milestones => 'Milestones',
            self::Management => 'Project Management Plan',
            self::Design => 'Design',
            self::Procurement => 'Procurement',
            self::Construction => 'Construction',
            self::Commissioning => 'Commissioning',
        };
    }
}