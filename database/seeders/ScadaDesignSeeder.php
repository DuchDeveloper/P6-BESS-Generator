<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class ScadaDesignSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── RTU / Network Architecture ───────────────────────────
        $this->createDesignPackage([
            'code' => 'scada_rtu_network_arch',
            'name' => 'RTU / Network Architecture',
            'discipline' => Discipline::Scada,
            'maturity_level' => null,
            'is_optional' => false,
            'is_conditional' => true,
            'condition_flag' => 'scada_included',
            'output_key' => 'scada_rtu_network_arch_approved',
            'depends_on' => 'bod_concept_ga_approved',
            'durations' => [2, 8, 8, 5, 1, 0],
            'sort_order' => 40,
        ]);

        // ── SCADA Maturity Chains ────────────────────────────────
        $chains = [
            [
                'code_prefix' => 'scada_tpl',
                'name' => 'Telemetry and Point List Design',
                'output_prefix' => 'scada',
                'output_suffix' => 'tpl_approved',
                'is_conditional' => true,
                'condition_flag' => 'scada_included',
                'sort_order' => 41,
            ],
            [
                'code_prefix' => 'scada_fiber',
                'name' => 'Fiber / Communications Link Design',
                'output_prefix' => 'scada',
                'output_suffix' => 'fiber_approved',
                'is_conditional' => true,
                'condition_flag' => 'scada_included',
                'sort_order' => 42,
            ],
        ];

        foreach ($chains as $chain) {
            $this->createMaturityChain(array_merge($chain, [
                'discipline' => Discipline::Scada,
                'entry_anchor' => 'scada_rtu_network_arch_approved',
            ]));
        }
    }
}