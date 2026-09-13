<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class FireDesignSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Fire Basis of Design ─────────────────────────────────
        // Conditional: not applicable if fire included in vendor switchroom
        $this->createDesignPackage([
            'code' => 'fire_basis',
            'name' => 'Fire Basis of Design',
            'discipline' => Discipline::Fire,
            'maturity_level' => null,
            'is_optional' => true,
            'is_conditional' => true,
            'condition_flag' => 'fire_in_vendor_package',
            'output_key' => 'fire_basis_approved',
            'depends_on' => 'bod_concept_ga_approved',
            'durations' => [2, 5, 5, 3, 1, 0],
            'sort_order' => 60,
        ]);

        // ── Fire Maturity Chain ──────────────────────────────────
        $this->createMaturityChain([
            'code_prefix' => 'fire_sr',
            'name' => 'Switchroom Fire Detection and Alarm Design',
            'discipline' => Discipline::Fire,
            'entry_anchor' => 'fire_basis_approved',
            'output_prefix' => 'fire',
            'output_suffix' => 'sr_approved',
            'is_conditional' => true,
            'condition_flag' => 'fire_in_vendor_package',
            'sort_order' => 61,
        ]);
    }
}