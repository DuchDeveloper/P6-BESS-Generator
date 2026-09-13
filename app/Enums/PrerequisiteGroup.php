<?php

declare(strict_types=1);

namespace App\Enums;

enum PrerequisiteGroup: string
{
    case Completion = 'completion';
    case ElectricalTesting = 'electrical_testing';
    case SafetyAuxiliaries = 'safety_auxiliaries';
    case ControlsCommunications = 'controls_communications';
    case ApprovalAuthority = 'approval_authority';
    case NetworkInterface = 'network_interface';

    public function label(): string
    {
        return match ($this) {
            self::Completion => 'Completion',
            self::ElectricalTesting => 'Electrical Testing',
            self::SafetyAuxiliaries => 'Safety & Auxiliaries',
            self::ControlsCommunications => 'Controls & Communications',
            self::ApprovalAuthority => 'Approval / Authority',
            self::NetworkInterface => 'Network Interface',
        };
    }
}