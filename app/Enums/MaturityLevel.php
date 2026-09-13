<?php

declare(strict_types=1);

namespace App\Enums;

enum MaturityLevel: string
{
    case Basis = 'basis';
    case Thirty = '30';
    case Sixty = '60';
    case Ninety = '90';
    case Ifc = 'ifc';
    case IfcAfc = 'ifc_afc';
    case Freeze = 'freeze';

    public function label(): string
    {
        return match ($this) {
            self::Basis => 'Basis of Design',
            self::Thirty => '30% Design',
            self::Sixty => '60% Design',
            self::Ninety => '90% Design',
            self::Ifc => 'Issued for Construction',
            self::IfcAfc => 'IFC / AFC',
            self::Freeze => 'Design Freeze',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Basis => 0,
            self::Thirty => 1,
            self::Sixty => 2,
            self::Ninety => 3,
            self::Ifc => 4,
            self::IfcAfc => 5,
            self::Freeze => 6,
        };
    }
}