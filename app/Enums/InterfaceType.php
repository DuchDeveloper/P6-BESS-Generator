<?php

declare(strict_types=1);

namespace App\Enums;

enum InterfaceType: string
{
    case Power = 'power';
    case Control = 'control';
    case Communications = 'communications';
    case Protection = 'protection';
    case Auxiliary = 'auxiliary';
    case Energisation = 'energisation';
    case Commissioning = 'commissioning';

    public function label(): string
    {
        return match ($this) {
            self::Power => 'Power',
            self::Control => 'Control',
            self::Communications => 'Communications',
            self::Protection => 'Protection',
            self::Auxiliary => 'Auxiliary',
            self::Energisation => 'Energisation',
            self::Commissioning => 'Commissioning',
        };
    }
}