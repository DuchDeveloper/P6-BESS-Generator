<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActivityTemplate;
use App\Models\DependencyRule;
use App\Models\OutputDefinition;
use App\Models\PackageTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageLibrarySeeder extends Seeder
{
    /**
     * Seed the complete BESS package template library.
     *
     * Order matters: dependency rules reference output_keys from earlier seeders.
     * Wrapped in a transaction so a FK failure during the truncate phase rolls
     * back rather than leaving the library half-wiped (templates present but
     * without activities / outputs / rules).
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            DependencyRule::query()->delete();
            OutputDefinition::query()->delete();
            ActivityTemplate::query()->delete();
            PackageTemplate::query()->delete();

            $this->call([
            // Milestones (Section 3)
            MilestonesSeeder::class,

            // Project Management Plan (Section 3)
            ProjectManagementSeeder::class,

            // Design discipline seeders (Section 7)
            CivilDesignSeeder::class,
            ElectricalPrimarySeeder::class,
            ElectricalSecondarySeeder::class,
            ScadaDesignSeeder::class,
            HvacDesignSeeder::class,
            FireDesignSeeder::class,
            CommissioningDesignSeeder::class,

            // Design Management (Section 7.9)
            DesignManagementSeeder::class,

            // Procurement (Section 8)
            ProcurementSeeder::class,

            // Construction (Section 9)
            ConstructionSeeder::class,

            // Substation Civil work packages (SUB.CIV.SY / SR / CR) — MT
            // lives in ConstructionSeeder as con_tx_civil, reclassified.
            SubstationCivilSeeder::class,

            // Balance of Plant construction packages (toggleable via project scope flags)
            BopScopeSeeder::class,

            // Transformer Electrical Works, Testing and Commissioning
            TransformerElectricalSeeder::class,

            // Commissioning (Section 10)
            CommissioningPackageSeeder::class,
            ]);
        });
    }
}