<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\MaturityLevel;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class DesignManagementSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── 30% Multidiscipline Design Review ────────────────────
        $this->createDesignPackage([
            'code' => 'dm_30_review',
            'name' => '30% Multidiscipline Design Review',
            'type' => PackageType::Management,
            'discipline' => Discipline::DesignManagement,
            'wbs_category' => WbsCategory::Design,
            'maturity_level' => MaturityLevel::Thirty,
            'is_optional' => false,
            'has_dynamic_predecessors' => true,
            'dynamic_predecessor_maturity' => MaturityLevel::Thirty,
            'output_key' => 'dm_30_review_approved',
            'durations' => [2, 5, 5, 3, 1, 0],
            'sort_order' => 80,
        ]);

        // ── 60% Multidiscipline Design Review ────────────────────
        $this->createDesignPackage([
            'code' => 'dm_60_review',
            'name' => '60% Multidiscipline Design Review',
            'type' => PackageType::Management,
            'discipline' => Discipline::DesignManagement,
            'wbs_category' => WbsCategory::Design,
            'maturity_level' => MaturityLevel::Sixty,
            'is_optional' => false,
            'has_dynamic_predecessors' => true,
            'dynamic_predecessor_maturity' => MaturityLevel::Sixty,
            'output_key' => 'dm_60_review_approved',
            'depends_on' => 'dm_30_review_approved',
            'durations' => [2, 5, 5, 3, 1, 0],
            'sort_order' => 81,
        ]);

        // ── 90% Multidiscipline Design Review ────────────────────
        $this->createDesignPackage([
            'code' => 'dm_90_review',
            'name' => '90% Multidiscipline Design Review',
            'type' => PackageType::Management,
            'discipline' => Discipline::DesignManagement,
            'wbs_category' => WbsCategory::Design,
            'maturity_level' => MaturityLevel::Ninety,
            'is_optional' => false,
            'has_dynamic_predecessors' => true,
            'dynamic_predecessor_maturity' => MaturityLevel::Ninety,
            'output_key' => 'dm_90_review_approved',
            'depends_on' => 'dm_60_review_approved',
            'durations' => [2, 5, 5, 3, 1, 0],
            'sort_order' => 82,
        ]);

        // ── IFC / Final Multidiscipline Design Review ────────────
        $this->createDesignPackage([
            'code' => 'dm_ifc_review',
            'name' => 'IFC / Final Multidiscipline Design Review',
            'type' => PackageType::Management,
            'discipline' => Discipline::DesignManagement,
            'wbs_category' => WbsCategory::Design,
            'maturity_level' => MaturityLevel::Ifc,
            'is_optional' => false,
            'has_dynamic_predecessors' => true,
            'dynamic_predecessor_maturity' => MaturityLevel::Ifc,
            'output_key' => 'dm_ifc_review_approved',
            'depends_on' => 'dm_90_review_approved',
            'durations' => [2, 5, 5, 3, 1, 0],
            'sort_order' => 83,
        ]);

        // ── Design Freeze / Overall Design Complete ──────────────
        $this->createDesignPackage([
            'code' => 'design_freeze',
            'name' => 'Design Freeze / Overall Design Complete',
            'type' => PackageType::Management,
            'discipline' => Discipline::DesignManagement,
            'wbs_category' => WbsCategory::Design,
            'maturity_level' => MaturityLevel::Freeze,
            'is_optional' => false,
            'has_dynamic_predecessors' => true,
            'dynamic_predecessor_maturity' => MaturityLevel::Ifc,
            'output_key' => 'design_freeze_approved',
            'depends_on' => 'dm_ifc_review_approved',
            'durations' => [2, 3, 3, 2, 1, 0],
            'sort_order' => 84,
        ]);
    }
}