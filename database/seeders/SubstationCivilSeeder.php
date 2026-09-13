<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\OutputType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

/**
 * Substation Civil work packages per civil.md.
 *
 * WBS placement: Construction > Civil > Substation Civil > {MT | SY | SR | CR}
 * All four packages share construction_wbs_group = 'sub_civ' so the WBS
 * builder can group them under a single Substation Civil node.
 *
 * SUB.CIV.MT (Main Transformer Civil Works) is not seeded here — the existing
 * `con_tx_civil` package in ConstructionSeeder already carries the detailed
 * MT civil activities. It is reclassified (in ConstructionSeeder) to
 * construction_wbs_group = 'sub_civ' so it routes into the same WBS branch.
 */
class SubstationCivilSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        $this->seedSwitchyardCivil();
        $this->seedSwitchroomCivil();
        $this->seedControlRoomCivil();
    }

    // ── SUB.CIV.SY — Switchyard Civil Works ──────────────────
    // Single-line activities per equipment type. Shunt Reactor and
    // Capacitor Bank foundations are included but flagged to be pruned
    // downstream when not applicable.

    private function seedSwitchyardCivil(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_sub_civ_sy',
                'name' => 'Switchyard Civil Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_civ',
                'is_conditional' => true,
                'condition_flag' => 'substation_exists',
                'output_key' => 'sub_civ_sy_signoff',
                'output_name' => 'Switchyard Civil Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['site_mob_complete', 'civil_ifc_eqpad_approved'],
                'sort_order' => 130,
            ],
            [
                ['name' => 'Circuit Breaker foundations', 'duration' => 3],
                ['name' => 'Disconnector / Isolator foundations', 'duration' => 2],
                ['name' => 'Earth Switch foundations', 'duration' => 1],
                ['name' => 'Current Transformer foundations', 'duration' => 2],
                ['name' => 'Voltage Transformer / CVT foundations', 'duration' => 2],
                ['name' => 'Surge Arrester foundations', 'duration' => 1],
                ['name' => 'Line Trap / Wave Trap foundations', 'duration' => 1],
                ['name' => 'Busbar Support Structures / Gantries', 'duration' => 4],
                ['name' => 'Post Insulator / Support Column foundations', 'duration' => 2],
                ['name' => 'Shunt Reactor foundations', 'duration' => 3],
                ['name' => 'Capacitor Bank foundations', 'duration' => 3],
                ['name' => 'Switchyard Civil Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── SUB.CIV.SR — Switchroom Civil Works ──────────────────
    // Detailed activity list per civil.md (control room / building civil
    // block, lines 32-44 — the one labelled "Switchyard" but describing a
    // building with switchgear plinths, which the user confirmed is the
    // switchroom / e-house).

    private function seedSwitchroomCivil(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_sub_civ_sr',
                'name' => 'Switchroom Civil Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_civ',
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                // Preserve existing output_key so downstream switchroom
                // mech / elec / P&C packages stay wired.
                'output_key' => 'sr_civil_signoff',
                'output_name' => 'Switchroom Civil Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['site_mob_complete', 'civil_ifc_sr_fdn_approved'],
                'sort_order' => 131,
            ],
            [
                ['name' => 'Survey and setout of building footprint', 'duration' => 2],
                ['name' => 'Site clearing and stripping topsoil', 'duration' => 2],
                ['name' => 'Bulk earthworks to platform level', 'duration' => 4],
                ['name' => 'Piling or strip footings', 'duration' => 5],
                ['name' => 'Pile caps or footing pours', 'duration' => 3],
                ['name' => 'Ground beam rebar, formwork, pour', 'duration' => 4],
                ['name' => 'Under-slab services rough-in', 'duration' => 3],
                ['name' => 'Ground floor slab rebar and formwork', 'duration' => 3],
                ['name' => 'Ground floor slab pour and cure', 'duration' => 8],
                ['name' => 'Cable basement / pit construction and cast-in sleeves', 'duration' => 4],
                ['name' => 'Switchgear plinth pads (civil scope)', 'duration' => 2],
                ['name' => 'External ramps, stairs, walkways', 'duration' => 3],
                ['name' => 'Switchroom Civil Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── SUB.CIV.CR — Control Room Civil Works ─────────────────
    // Detailed activity list per civil.md lines 18-30.

    private function seedControlRoomCivil(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_sub_civ_cr',
                'name' => 'Control Room Civil Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_civ',
                'is_conditional' => true,
                'condition_flag' => 'control_room_exists',
                'output_key' => 'cr_civil_signoff',
                'output_name' => 'Control Room Civil Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['site_mob_complete', 'civil_ifc_cr_fdn_approved'],
                'sort_order' => 132,
            ],
            [
                ['name' => 'Survey and setout of building footprint', 'duration' => 2],
                ['name' => 'Site clearing and stripping topsoil', 'duration' => 2],
                ['name' => 'Bulk earthworks to platform level', 'duration' => 4],
                ['name' => 'Piling or strip footings (per geotech)', 'duration' => 5],
                ['name' => 'Pile caps or footing pours', 'duration' => 3],
                ['name' => 'Ground beam rebar, formwork, pour', 'duration' => 4],
                ['name' => 'Under-slab services rough-in (earth mat tails, conduits, drainage)', 'duration' => 3],
                ['name' => 'Ground floor slab rebar and formwork', 'duration' => 3],
                ['name' => 'Ground floor slab pour and cure', 'duration' => 8],
                ['name' => 'Cable basement construction and cast-in penetration sleeves', 'duration' => 4],
                ['name' => 'HVAC plinth pads (civil scope)', 'duration' => 2],
                ['name' => 'External ramps, stairs, walkways', 'duration' => 3],
                ['name' => 'Control Room Civil Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }
}