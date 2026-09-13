<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentNodeType: string
{
    case BatteryGroup = 'battery_group';
    case PcsGroup = 'pcs_group';
    case Sut = 'sut';
    case Rmu = 'rmu';
    case BlockCollectorRmu = 'block_collector_rmu';
    case ZoneCollectorRmu = 'zone_collector_rmu';
    case Switchroom = 'switchroom';
    case ControlRoom = 'control_room';
    case Transformer = 'transformer';
    case Substation = 'substation';
    case RtuPanel = 'rtu_panel';
    case UpsDc = 'ups_dc';
    case Hvac = 'hvac';
    case FirePanel = 'fire_panel';

    public function label(): string
    {
        return match ($this) {
            self::BatteryGroup => 'Battery Group',
            self::PcsGroup => 'PCS Group',
            self::Sut => 'Step-Up Transformer (SUT)',
            self::Rmu => 'Ring Main Unit (RMU)',
            self::BlockCollectorRmu => 'Block Collector RMU',
            self::ZoneCollectorRmu => 'Zone Collector RMU',
            self::Switchroom => 'Switchroom',
            self::ControlRoom => 'Control Room',
            self::Transformer => 'Main Transformer',
            self::Substation => 'Substation',
            self::RtuPanel => 'RTU / SCADA Panel',
            self::UpsDc => 'UPS / DC System',
            self::Hvac => 'HVAC',
            self::FirePanel => 'Fire Panel',
        };
    }

    public function isTopologyDriven(): bool
    {
        return in_array($this, [
            self::BatteryGroup, self::PcsGroup, self::Sut,
            self::Rmu, self::BlockCollectorRmu, self::ZoneCollectorRmu,
        ]);
    }
}