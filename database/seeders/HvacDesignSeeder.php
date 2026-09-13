<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class HvacDesignSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── HVAC Basis of Design ─────────────────────────────────
        // Conditional: not applicable if HVAC included in vendor switchroom package
        $this->createDesignPackage([
            'code' => 'hvac_basis',
            'name' => 'HVAC Basis of Design',
            'discipline' => Discipline::Hvac,
            'maturity_level' => null,
            'is_optional' => true,
            'is_conditional' => true,
            'condition_flag' => 'hvac_in_vendor_package',
            'output_key' => 'hvac_basis_approved',
            'depends_on' => 'bod_concept_ga_approved',
            'durations' => [2, 5, 5, 3, 1, 0],
            'sort_order' => 50,
        ]);

        // ── HVAC Maturity Chains ─────────────────────────────────
        $chains = [
            [
                'code_prefix' => 'hvac_sr',
                'name' => 'Switchroom HVAC Design',
                'output_prefix' => 'hvac',
                'output_suffix' => 'sr_approved',
                'is_conditional' => true,
                'condition_flag' => 'hvac_in_vendor_package',
                'sort_order' => 51,
            ],
            [
                'code_prefix' => 'hvac_cr',
                'name' => 'Control Room HVAC Design',
                'output_prefix' => 'hvac',
                'output_suffix' => 'cr_approved',
                'is_conditional' => true,
                'condition_flag' => 'control_room_exists',
                'sort_order' => 52,
            ],
        ];

        foreach ($chains as $chain) {
            $this->createMaturityChain(array_merge($chain, [
                'discipline' => Discipline::Hvac,
                'entry_anchor' => 'hvac_basis_approved',
            ]));
        }
    }
}