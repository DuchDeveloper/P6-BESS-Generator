<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class ElectricalPrimarySeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Concept SLD ──────────────────────────────────────────
        $this->createDesignPackage([
            'code' => 'elec_p_concept_sld',
            'name' => 'Concept Single Line Diagram',
            'discipline' => Discipline::ElectricalPrimary,
            'maturity_level' => null,
            'is_optional' => false,
            'output_key' => 'elec_primary_concept_sld_approved',
            'depends_on' => 'bod_concept_ga_approved',
            'durations' => [2, 8, 8, 5, 1, 0],
            'sort_order' => 20,
        ]);

        // ── Electrical Primary Maturity Chains ───────────────────
        $chains = [
            [
                'code_prefix' => 'elec_p_feeder',
                'name' => 'Collector / Feeder Design',
                'output_prefix' => 'elec_p',
                'output_suffix' => 'feeder_approved',
                'sort_order' => 21,
            ],
            [
                'code_prefix' => 'elec_p_earth',
                'name' => 'Earthing Design',
                'output_prefix' => 'elec_p',
                'output_suffix' => 'earth_approved',
                'sort_order' => 22,
            ],
            [
                'code_prefix' => 'elec_p_lp',
                'name' => 'Lightning Protection Design',
                'output_prefix' => 'elec_p',
                'output_suffix' => 'lp_approved',
                'sort_order' => 23,
            ],
            [
                'code_prefix' => 'elec_p_sr_ifc',
                'name' => 'Switchroom Interface Design',
                'output_prefix' => 'elec_p',
                'output_suffix' => 'sr_ifc_approved',
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'sort_order' => 24,
            ],
            [
                'code_prefix' => 'elec_p_sub_ifc',
                'name' => 'Substation Interface Design',
                'output_prefix' => 'elec_p',
                'output_suffix' => 'sub_ifc_approved',
                'is_conditional' => true,
                'condition_flag' => 'substation_exists',
                'sort_order' => 25,
            ],
        ];

        foreach ($chains as $chain) {
            $this->createMaturityChain(array_merge($chain, [
                'discipline' => Discipline::ElectricalPrimary,
                'entry_anchor' => 'elec_primary_concept_sld_approved',
            ]));
        }
    }
}