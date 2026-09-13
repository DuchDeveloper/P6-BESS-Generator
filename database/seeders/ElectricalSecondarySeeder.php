<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class ElectricalSecondarySeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Protection Philosophy ────────────────────────────────
        $this->createDesignPackage([
            'code' => 'elec_s_protection_philosophy',
            'name' => 'Protection Philosophy',
            'discipline' => Discipline::ElectricalSecondary,
            'maturity_level' => null,
            'is_optional' => false,
            'output_key' => 'elec_s_protection_philosophy_approved',
            'depends_on' => 'elec_primary_concept_sld_approved',
            'durations' => [2, 8, 8, 5, 1, 0],
            'sort_order' => 30,
        ]);

        // ── Electrical Secondary Maturity Chains ─────────────────
        $chains = [
            [
                'code_prefix' => 'elec_s_relay',
                'name' => 'Relay and Interlocking Design',
                'output_prefix' => 'elec_s',
                'output_suffix' => 'relay_approved',
                'sort_order' => 31,
            ],
            [
                'code_prefix' => 'elec_s_meter',
                'name' => 'Metering Design',
                'output_prefix' => 'elec_s',
                'output_suffix' => 'meter_approved',
                'sort_order' => 32,
            ],
        ];

        foreach ($chains as $chain) {
            $this->createMaturityChain(array_merge($chain, [
                'discipline' => Discipline::ElectricalSecondary,
                'entry_anchor' => 'elec_s_protection_philosophy_approved',
            ]));
        }
    }
}