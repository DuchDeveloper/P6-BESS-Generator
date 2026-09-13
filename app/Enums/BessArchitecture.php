<?php

declare(strict_types=1);

namespace App\Enums;

enum BessArchitecture: string
{
    case IntegratedContainer = 'integrated_container';
    case SeparatePcsOnePerSut = 'separate_pcs_one_per_sut';
    case SeparatePcsMultiPerSut = 'separate_pcs_multi_per_sut';
    case NoSut = 'no_sut';
    case Cluster = 'cluster';

    public function label(): string
    {
        return match ($this) {
            self::IntegratedContainer => 'Architecture 1 — Integrated Container',
            self::SeparatePcsOnePerSut => 'Architecture 2 — Separate PCS, One per SUT',
            self::SeparatePcsMultiPerSut => 'Architecture 3 — Separate PCS, Multiple per SUT',
            self::NoSut => 'Architecture 4 — No SUT (Direct MV PCS Output)',
            self::Cluster => 'Architecture 5 — Cluster (Multiple Blocks share one SUT)',
        };
    }

    public function generatesPcsInstall(): bool
    {
        return $this !== self::IntegratedContainer;
    }

    public function generatesDcCables(): bool
    {
        return $this !== self::IntegratedContainer;
    }

    public function generatesAcCables(): bool
    {
        return $this !== self::NoSut;
    }

    public function generatesSut(): bool
    {
        return $this !== self::NoSut;
    }

    public function isCrossBlockSut(): bool
    {
        return $this === self::Cluster;
    }

    public function disablesRmu(): bool
    {
        return $this === self::NoSut;
    }
}