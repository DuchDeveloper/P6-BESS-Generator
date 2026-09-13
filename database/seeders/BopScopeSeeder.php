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
 * Balance of Plant (BOP) scope packages. Each package is gated by a
 * project-level scope flag (e.g. `perimeter_fencing_exists`) and suppressed
 * when the flag is false.
 *
 * Cross-BOP dependencies are modelled as soft (`mandatory => false`) so a
 * package that references, say, `site_comms_backbone_complete` does not
 * block validation when the user hasn't enabled the comms backbone scope.
 * Hard prerequisites like `site_mob_complete` remain mandatory.
 */
class BopScopeSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        $this->seedBuildings();
        $this->seedSiteCivil();
        $this->seedSiteEi();
        $this->seedSafetyAndFire();
    }

    // ── BOP.CIV.* — Buildings ────────────────────────────────
    // Six building packages per BOP.md. Each follows the same 5-stage spine:
    //   1. Earthworks and foundations
    //   2. Slab on grade
    //   3. Structure and envelope
    //   4. Internal fit-out / External works (varies)
    //   5. Practical completion
    // All tagged construction_wbs_group = 'bop_buildings' so they cluster
    // under Construction > BOP > Buildings in the WBS.

    private function seedBuildings(): void
    {
        // BOP.CIV.MO — Main Office Building
        $this->createBopBuilding(
            code: 'con_bop_site_office',
            name: 'Main Office Building',
            conditionFlag: 'site_office_exists',
            outputKey: 'site_office_complete',
            outputName: 'Main Office Building Practical Completion',
            sortOrder: 200,
            stageTwoName: 'Slab on grade (under-slab services, reinforcement, pour, cure)',
            stageThreeName: 'Structure and envelope (columns, walls, roof, windows, finishes)',
            stageFourName: 'Internal fit-out (partitions, ceilings, flooring, joinery, paint, wet areas)',
            durations: [5, 6, 10, 10, 2],
        );

        // BOP.CIV.WH — Warehouse Building
        $this->createBopBuilding(
            code: 'con_bop_oam_bldg',
            name: 'Warehouse / O&M Building',
            conditionFlag: 'oam_building_exists',
            outputKey: 'oam_building_complete',
            outputName: 'Warehouse Building Practical Completion',
            sortOrder: 201,
            stageTwoName: 'Heavy-duty slab on grade (subgrade, reinforcement, pour, joint sealing)',
            stageThreeName: 'Structure and envelope (portal frame, tilt panels, roof, roller doors)',
            stageFourName: 'External works (hardstand, loading dock, drainage, linemarking)',
            durations: [6, 8, 12, 8, 2],
        );

        // BOP.CIV.AB — Ablution / Amenities Block
        $this->createBopBuilding(
            code: 'con_bop_ablutions',
            name: 'Ablution / Amenities Block',
            conditionFlag: 'ablutions_building_exists',
            outputKey: 'ablutions_complete',
            outputName: 'Ablution Block Practical Completion',
            sortOrder: 202,
            stageTwoName: 'Slab and hydraulic rough-in (under-slab drainage, floor wastes, pour)',
            stageThreeName: 'Structure and envelope (blockwork, roof, doors, windows)',
            stageFourName: 'Wet area fit-out (waterproofing, tiling, sanitaryware interface)',
            durations: [3, 4, 6, 6, 2],
        );

        // BOP.CIV.WS — Workshop / Maintenance Building
        $this->createBopBuilding(
            code: 'con_bop_workshop',
            name: 'Workshop / Maintenance Building',
            conditionFlag: 'workshop_building_exists',
            outputKey: 'workshop_complete',
            outputName: 'Workshop Practical Completion',
            sortOrder: 203,
            stageTwoName: 'Slab on grade (wash bay falls, separator pit, reinforcement, pour)',
            stageThreeName: 'Structure and envelope (portal frame, walls, roof, crane gantry supports)',
            stageFourName: 'Internal works (workshop fit-out, services interface, linemarking)',
            durations: [5, 6, 10, 6, 2],
        );

        // BOP.CIV.GH — Guard House / Security Building
        $this->createBopBuilding(
            code: 'con_bop_guardhouse',
            name: 'Guard House / Security Building',
            conditionFlag: 'guardhouse_exists',
            outputKey: 'guardhouse_complete',
            outputName: 'Guard House Practical Completion',
            sortOrder: 204,
            stageTwoName: 'Slab on grade (under-slab services, reinforcement, pour)',
            stageThreeName: 'Structure and envelope (blockwork, roof, doors, windows, external finish)',
            stageFourName: 'Internal fit-out (partitions, finishes, boom gate / turnstile interface)',
            durations: [2, 3, 6, 4, 1],
        );

        // BOP.CIV.FS — Fire / Pump House Building
        $this->createBopBuilding(
            code: 'con_bop_fire_pump_house',
            name: 'Fire / Pump House Building',
            conditionFlag: 'fire_pump_house_exists',
            outputKey: 'fire_pump_house_complete',
            outputName: 'Fire Pump House Practical Completion',
            sortOrder: 205,
            stageTwoName: 'Slab and containment (pump house slab, bund walls, cure)',
            stageThreeName: 'Structure and envelope (blockwork, roof, access doors, louvres)',
            stageFourName: 'External works (firewater reticulation interface, tank interface)',
            durations: [4, 5, 7, 5, 2],
        );
    }

    /**
     * Shared 5-stage building spine per BOP.md. Stage 1 and Stage 5 names
     * are fixed; Stages 2/3/4 vary per building (slab form, envelope type,
     * fit-out scope).
     *
     * @param  array<int, int>  $durations  [stage1, stage2, stage3, stage4, stage5] in days
     */
    private function createBopBuilding(
        string $code,
        string $name,
        string $conditionFlag,
        string $outputKey,
        string $outputName,
        int $sortOrder,
        string $stageTwoName,
        string $stageThreeName,
        string $stageFourName,
        array $durations,
    ): void {
        $this->createCustomPackage(
            [
                'code' => $code,
                'name' => $name,
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_buildings',
                'is_conditional' => true,
                'condition_flag' => $conditionFlag,
                'output_key' => $outputKey,
                'output_name' => $outputName,
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => $sortOrder,
            ],
            [
                ['name' => 'Earthworks and foundations (setout, excavation, piling, footings)', 'duration' => $durations[0]],
                ['name' => $stageTwoName, 'duration' => $durations[1]],
                ['name' => $stageThreeName, 'duration' => $durations[2]],
                ['name' => $stageFourName, 'duration' => $durations[3]],
                ['name' => 'Practical completion (final clean, inspection, handover)', 'duration' => $durations[4], 'is_milestone' => true],
            ]
        );
    }

    // ── Site-wide civil / infrastructure ────────────────────

    private function seedSiteCivil(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_bop_perimeter_fence',
                'name' => 'Site Perimeter Fencing and Gates',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_civil',
                'is_conditional' => true,
                'condition_flag' => 'perimeter_fencing_exists',
                'output_key' => 'perimeter_fencing_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => 210,
            ],
            [
                ['name' => 'Fence Line Survey and Set-Out', 'duration' => 2],
                ['name' => 'Post and Gate Footings', 'duration' => 5],
                ['name' => 'Fence Panel and Gate Installation', 'duration' => 8],
                ['name' => 'Perimeter Fencing Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_site_drainage',
                'name' => 'Site Drainage and Stormwater',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_civil',
                'is_conditional' => true,
                'condition_flag' => 'site_drainage_exists',
                'output_key' => 'site_drainage_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => 211,
            ],
            [
                ['name' => 'Drainage Route Survey and Set-Out', 'duration' => 2],
                ['name' => 'Earthworks and Trenching', 'duration' => 8],
                ['name' => 'Pipe, Pit and Swale Installation', 'duration' => 8],
                ['name' => 'Backfill and Reinstatement', 'duration' => 3],
                ['name' => 'Site Drainage Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_access_roads',
                'name' => 'Internal Access Roads and Hardstands',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_civil',
                'is_conditional' => true,
                'condition_flag' => 'access_roads_exists',
                'output_key' => 'access_roads_complete',
                'output_type' => OutputType::CompletionMilestone,
                // Roads typically go on top of drainage — soft because the user may
                // not include drainage on a small retrofit.
                'depends_on' => [
                    'site_mob_complete',
                    ['key' => 'site_drainage_complete', 'mandatory' => false],
                ],
                'sort_order' => 212,
            ],
            [
                ['name' => 'Road Alignment Survey and Set-Out', 'duration' => 2],
                ['name' => 'Subgrade Preparation', 'duration' => 5],
                ['name' => 'Sub-base and Base Course', 'duration' => 6],
                ['name' => 'Surfacing and Linemarking', 'duration' => 4],
                ['name' => 'Access Roads Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_potable_water',
                'name' => 'Potable Water Supply',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_civil',
                'is_conditional' => true,
                'condition_flag' => 'potable_water_exists',
                'output_key' => 'potable_water_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => 213,
            ],
            [
                ['name' => 'Water Route Survey and Trenching', 'duration' => 3],
                ['name' => 'Pipe and Tank Installation', 'duration' => 5],
                ['name' => 'Pressure Test and Chlorination', 'duration' => 2],
                ['name' => 'Potable Water Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Site-wide E&I ────────────────────────────────────────

    private function seedSiteEi(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_bop_comms_backbone',
                'name' => 'Site Communications Backbone',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Scada,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_ei',
                'is_conditional' => true,
                'condition_flag' => 'site_comms_backbone_exists',
                'output_key' => 'site_comms_backbone_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => 220,
            ],
            [
                ['name' => 'Fibre Route Survey and Set-Out', 'duration' => 2],
                ['name' => 'Trenching and Conduit Installation', 'duration' => 6],
                ['name' => 'Fibre Cable Pull and Splicing', 'duration' => 5],
                ['name' => 'Core Network Switch Installation', 'duration' => 2],
                ['name' => 'End-to-End Network Test', 'duration' => 2],
                ['name' => 'Site Comms Backbone Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_external_lighting',
                'name' => 'Site External Lighting',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_ei',
                'is_conditional' => true,
                'condition_flag' => 'external_lighting_exists',
                'output_key' => 'external_lighting_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => [
                    'site_mob_complete',
                    ['key' => 'access_roads_complete', 'mandatory' => false],
                ],
                'sort_order' => 221,
            ],
            [
                ['name' => 'Lighting Layout Survey and Set-Out', 'duration' => 2],
                ['name' => 'Pole Foundations', 'duration' => 4],
                ['name' => 'Pole and Luminaire Installation', 'duration' => 5],
                ['name' => 'Cabling and Terminations', 'duration' => 4],
                ['name' => 'Lighting Commissioning', 'duration' => 2],
                ['name' => 'External Lighting Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_cctv_security',
                'name' => 'CCTV and Intrusion Detection',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Scada,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_ei',
                'is_conditional' => true,
                'condition_flag' => 'cctv_security_exists',
                'output_key' => 'cctv_security_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => [
                    'site_mob_complete',
                    ['key' => 'site_comms_backbone_complete', 'mandatory' => false],
                    ['key' => 'perimeter_fencing_complete', 'mandatory' => false],
                ],
                'sort_order' => 222,
            ],
            [
                ['name' => 'Camera and Sensor Layout', 'duration' => 2],
                ['name' => 'Cabling and Containment', 'duration' => 5],
                ['name' => 'Camera and Sensor Installation', 'duration' => 4],
                ['name' => 'NVR and VMS Configuration', 'duration' => 3],
                ['name' => 'CCTV and IDS Commissioning', 'duration' => 2],
                ['name' => 'CCTV and Security Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_access_control',
                'name' => 'Access Control and Automated Gates',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Scada,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_ei',
                'is_conditional' => true,
                'condition_flag' => 'access_control_exists',
                'output_key' => 'access_control_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => [
                    'site_mob_complete',
                    ['key' => 'perimeter_fencing_complete', 'mandatory' => false],
                    ['key' => 'site_comms_backbone_complete', 'mandatory' => false],
                ],
                'sort_order' => 223,
            ],
            [
                ['name' => 'Gate Motor and Reader Layout', 'duration' => 1],
                ['name' => 'Gate Motor Installation', 'duration' => 3],
                ['name' => 'Card Readers and Controllers', 'duration' => 3],
                ['name' => 'Access Control System Integration', 'duration' => 3],
                ['name' => 'Access Control Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_public_address',
                'name' => 'Public Address and Site Telephony',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Scada,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_ei',
                'is_conditional' => true,
                'condition_flag' => 'public_address_exists',
                'output_key' => 'public_address_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => [
                    'site_mob_complete',
                    ['key' => 'site_comms_backbone_complete', 'mandatory' => false],
                ],
                'sort_order' => 224,
            ],
            [
                ['name' => 'Speaker and Handset Layout', 'duration' => 1],
                ['name' => 'Cabling and Containment', 'duration' => 4],
                ['name' => 'Speaker and Handset Installation', 'duration' => 3],
                ['name' => 'PA Controller Integration and Test', 'duration' => 2],
                ['name' => 'Public Address Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_site_ups',
                'name' => 'Site UPS and Essential Services LV Board',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_site_ei',
                'is_conditional' => true,
                'condition_flag' => 'site_ups_exists',
                'output_key' => 'site_ups_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => 225,
            ],
            [
                ['name' => 'UPS and ESB Pad and Anchor', 'duration' => 2],
                ['name' => 'UPS and Battery Installation', 'duration' => 3],
                ['name' => 'Essential Services LV Board Installation', 'duration' => 2],
                ['name' => 'Cabling and Terminations', 'duration' => 3],
                ['name' => 'UPS Commissioning and Load Transfer Test', 'duration' => 2],
                ['name' => 'Site UPS Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Safety and fire ──────────────────────────────────────

    private function seedSafetyAndFire(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_bop_building_fire',
                'name' => 'Building Fire Systems (Sprinklers, Hydrants, Extinguishers)',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_safety',
                'is_conditional' => true,
                'condition_flag' => 'building_fire_system_exists',
                'output_key' => 'building_fire_system_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => [
                    'site_mob_complete',
                    ['key' => 'oam_building_complete', 'mandatory' => false],
                    ['key' => 'guardhouse_complete', 'mandatory' => false],
                    ['key' => 'control_room_construction_complete', 'mandatory' => false],
                ],
                'sort_order' => 230,
            ],
            [
                ['name' => 'Fire System Layout and Set-Out', 'duration' => 2],
                ['name' => 'Pipework and Sprinkler Installation', 'duration' => 6],
                ['name' => 'Hydrant and Extinguisher Installation', 'duration' => 3],
                ['name' => 'Fire Detection and Alarm Wiring', 'duration' => 4],
                ['name' => 'Fire System Commissioning and Certification', 'duration' => 3],
                ['name' => 'Building Fire Systems Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'con_bop_lightning_protection',
                'name' => 'Site Lightning Protection',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'bop_safety',
                'is_conditional' => true,
                'condition_flag' => 'lightning_protection_site_exists',
                'output_key' => 'lightning_protection_site_complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => 231,
            ],
            [
                ['name' => 'Lightning Mast Layout and Foundations', 'duration' => 4],
                ['name' => 'Mast Erection and Air Terminals', 'duration' => 4],
                ['name' => 'Down-Conductors and Earthing', 'duration' => 4],
                ['name' => 'Earth Resistance Testing', 'duration' => 1],
                ['name' => 'Lightning Protection Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }
}