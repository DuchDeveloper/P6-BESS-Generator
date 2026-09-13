<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryModel: string
{
    case Epc = 'epc';
    case BopFreeIssued = 'bop_free_issued';
    case OwnerProvidedDesign = 'owner_provided_design';
    case ConstructOnly = 'construct_only';
    case SplitContract = 'split_contract';

    public function label(): string
    {
        return match ($this) {
            self::Epc => 'EPC (Engineer, Procure, Construct)',
            self::BopFreeIssued => 'BOP Free-Issued',
            self::OwnerProvidedDesign => 'Owner-Provided Design',
            self::ConstructOnly => 'Construct Only',
            self::SplitContract => 'Split Contract',
        };
    }
}