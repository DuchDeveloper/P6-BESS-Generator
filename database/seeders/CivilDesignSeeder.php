<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class CivilDesignSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Root Package: Basis of Design / Concept GA ───────────
        $this->createDesignPackage([
            'code' => 'bod_concept_ga',
            'name' => 'Basis of Design / Concept GA',
            'discipline' => Discipline::Civil,
            'maturity_level' => null,
            'is_optional' => false,
            'output_key' => 'bod_concept_ga_approved',
            'depends_on' => ['ntp_received', 'pm_design_mgmt_plan_approved', 'pm_schedule_plan_approved'],
            'durations' => [2, 10, 10, 5, 1, 0],
            'sort_order' => 1,
        ]);

        // ── Civil Design Inputs Package ──────────────────────────
        $this->createDesignPackage([
            'code' => 'civil_design_inputs',
            'name' => 'Civil Design Inputs',
            'discipline' => Discipline::Civil,
            'maturity_level' => null,
            'is_optional' => false,
            'output_key' => 'civil_design_inputs_approved',
            'depends_on' => 'bod_concept_ga_approved',
            'durations' => [2, 5, 5, 3, 1, 0],
            'sort_order' => 10,
        ]);

        // ── Civil Maturity Chains ────────────────────────────────
        $chains = [
            [
                'code_prefix' => 'civil_eqpad',
                'name' => 'Equipment Pad Design',
                'output_prefix' => 'civil',
                'output_suffix' => 'eqpad_approved',
                'sort_order' => 11,
            ],
            [
                'code_prefix' => 'civil_bess_fdn',
                'name' => 'BESS Foundation Design',
                'output_prefix' => 'civil',
                'output_suffix' => 'bess_fdn_approved',
                'sort_order' => 12,
            ],
            [
                'code_prefix' => 'civil_sr_fdn',
                'name' => 'Switchroom Foundation Design',
                'output_prefix' => 'civil',
                'output_suffix' => 'sr_fdn_approved',
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'sort_order' => 13,
            ],
            [
                'code_prefix' => 'civil_cr_fdn',
                'name' => 'Control Room Foundation Design',
                'output_prefix' => 'civil',
                'output_suffix' => 'cr_fdn_approved',
                'is_conditional' => true,
                'condition_flag' => 'control_room_exists',
                'sort_order' => 14,
            ],
            [
                'code_prefix' => 'civil_drain',
                'name' => 'Drainage Design',
                'output_prefix' => 'civil',
                'output_suffix' => 'drain_approved',
                'sort_order' => 15,
            ],
            [
                'code_prefix' => 'civil_road',
                'name' => 'Road and Hardstand Design',
                'output_prefix' => 'civil',
                'output_suffix' => 'road_approved',
                'sort_order' => 16,
            ],
            [
                'code_prefix' => 'civil_fence',
                'name' => 'Fence and Security Civil Design',
                'output_prefix' => 'civil',
                'output_suffix' => 'fence_approved',
                'sort_order' => 17,
            ],
        ];

        foreach ($chains as $chain) {
            $this->createMaturityChain(array_merge($chain, [
                'discipline' => Discipline::Civil,
                'entry_anchor' => 'civil_design_inputs_approved',
            ]));
        }
    }
}