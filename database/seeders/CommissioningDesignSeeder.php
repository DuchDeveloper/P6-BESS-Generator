<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Models\DependencyRule;
use App\Models\PackageTemplate;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class CommissioningDesignSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Commissioning Basis and Strategy ─────────────────────
        $this->createDesignPackage([
            'code' => 'comm_basis_strategy',
            'name' => 'Commissioning Basis and Strategy',
            'discipline' => Discipline::CommissioningDocs,
            'maturity_level' => null,
            'is_optional' => false,
            'output_key' => 'comm_basis_strategy_approved',
            'depends_on' => 'bod_concept_ga_approved',
            'durations' => [2, 8, 8, 5, 1, 0],
            'sort_order' => 70,
        ]);

        // ── Commissioning Plan (30/60/90/IFC) ────────────────────
        $this->createMaturityChain([
            'code_prefix' => 'comm_plan',
            'name' => 'Commissioning Plan',
            'discipline' => Discipline::CommissioningDocs,
            'entry_anchor' => 'comm_basis_strategy_approved',
            'output_prefix' => 'comm_plan',
            'output_suffix' => 'approved',
            'sort_order' => 71,
        ]);

        // ── ITP / ITC Package (30/60/90/IFC) ─────────────────────
        // Depends on comm_plan_ifc_approved (the IFC commissioning plan)
        $this->createMaturityChain([
            'code_prefix' => 'itp',
            'name' => 'ITP / ITC Package',
            'discipline' => Discipline::CommissioningDocs,
            'entry_anchor' => 'comm_plan_ifc_approved',
            'output_prefix' => 'itp',
            'output_suffix' => 'approved',
            'sort_order' => 72,
        ]);

        // ITP 30% additionally gates on the Quality Management Plan — ITPs
        // are written against the QA framework set by the Quality Plan.
        $itp30 = PackageTemplate::where('code', 'itp_30')->first();
        if ($itp30) {
            DependencyRule::firstOrCreate([
                'consumer_template_id' => $itp30->id,
                'required_output_key' => 'pm_quality_plan_approved',
            ], ['is_mandatory' => true]);
        }
    }
}