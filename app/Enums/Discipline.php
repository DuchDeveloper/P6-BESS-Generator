<?php

declare(strict_types=1);

namespace App\Enums;

enum Discipline: string
{
    case Civil = 'civil';
    case ElectricalPrimary = 'electrical_primary';
    case ElectricalSecondary = 'electrical_secondary';
    case Scada = 'scada';
    case Hvac = 'hvac';
    case Fire = 'fire';
    case CommissioningDocs = 'commissioning_docs';
    case DesignManagement = 'design_management';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Civil => 'Civil',
            self::ElectricalPrimary => 'Electrical Primary',
            self::ElectricalSecondary => 'Electrical Secondary',
            self::Scada => 'SCADA / Communications',
            self::Hvac => 'Mechanical / HVAC',
            self::Fire => 'Fire Systems',
            self::CommissioningDocs => 'Commissioning Documentation',
            self::DesignManagement => 'Design Management',
            self::None => 'None',
        };
    }
}