<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\OutputType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class ConstructionSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        $this->seedSiteMobilisation();
        $this->seedTransformerCivilMechOil();
        $this->seedSwitchyardMechanical();
        $this->seedSwitchroomInstallation();
        $this->seedSubstationConstruction();
        $this->seedControlRoomInstallation();
        // Transformer electrical works, testing and commissioning are in
        // TransformerElectricalSeeder — called separately by PackageLibrarySeeder.
        // Topology activities (battery, PCS, SUT) are generated programmatically
        // by TopologyActivityGenerator, not from package templates.
        // Substation civil packages (SUB.CIV.SY / SR / CR) live in
        // SubstationCivilSeeder. The existing `con_tx_civil` below (SUB.CIV.MT)
        // is reclassified to construction_wbs_group = 'sub_civ' so all four
        // Substation Civil packages share one WBS node.
    }

    // ── Site Mobilisation ────────────────────────────────────

    private function seedSiteMobilisation(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_site_mob',
                'name' => 'Site Mobilisation',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'site_mobilisation',
                'is_optional' => false,
                'output_key' => 'site_mob_complete',
                'output_name' => 'Site Mobilisation Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['bod_concept_ga_approved', 'pm_hse_plan_approved', 'pm_risk_register_approved'],
                'sort_order' => 99,
            ],
            [
                ['name' => 'Site Survey and Set-Out', 'duration' => 3],
                ['name' => 'Establish Temporary Facilities and Site Office', 'duration' => 5],
                ['name' => 'Install Site Fencing and Security', 'duration' => 4],
                ['name' => 'Construct Temporary Access Roads', 'duration' => 5],
                ['name' => 'Environmental Controls and Erosion Protection', 'duration' => 3],
                ['name' => 'Mobilise Plant and Equipment', 'duration' => 3],
                ['name' => 'Site Induction and Safety Briefings', 'duration' => 2],
                ['name' => 'Site Mobilisation Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Main Transformer — Civil, Mechanical, Oil (Substation) ─

    private function seedTransformerCivilMechOil(): void
    {
        // Main Transformer Civil Works (SUB.CIV.MT) — grouped with the
        // other Substation Civil packages via construction_wbs_group = 'sub_civ'.
        $this->createCustomPackage(
            [
                'code' => 'con_tx_civil',
                'name' => 'Main Transformer Civil Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::Civil,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_civ',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_civil_signoff',
                'output_name' => 'Transformer Civil Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['civil_ifc_sr_fdn_approved', 'site_mob_complete'],
                'sort_order' => 100,
            ],
            [
                ['name' => 'Transformer Pad Survey and Set-Out', 'duration' => 2],
                ['name' => 'Blinding Concrete', 'duration' => 1],
                ['name' => 'Foundation Pour', 'duration' => 1],
                ['name' => 'Concrete Curing', 'duration' => 7],
                ['name' => 'Bund Wall and Oil Trench Construction', 'duration' => 5],
                ['name' => 'Transformer Civil Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Mechanical Installation
        $this->createCustomPackage(
            [
                'code' => 'con_tx_mech',
                'name' => 'Transformer Mechanical Installation',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_mech',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_mech_signoff',
                'output_name' => 'Transformer Mechanical Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['tx_civil_signoff', 'transformer_delivered'],
                'sort_order' => 101,
            ],
            [
                ['name' => 'Crane Lift and Positioning', 'duration' => 2],
                ['name' => 'Mechanical Fittings Installation', 'duration' => 3],
                ['name' => 'Instruments and Gauges Installation', 'duration' => 2],
                ['name' => 'Transformer Mechanical Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Oil Filling and Treatment
        $this->createCustomPackage(
            [
                'code' => 'con_tx_oil',
                'name' => 'Transformer Oil Filling and Treatment',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_mech',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_oil_complete',
                'output_name' => 'Oil Filling Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'tx_mech_signoff',
                'sort_order' => 102,
            ],
            [
                ['name' => 'Oil Filtration and Degassing', 'duration' => 3],
                ['name' => 'Oil Fill, Settle and Test', 'duration' => 3],
                ['name' => 'Oil Filling Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Electrical works, P&C, testing and commissioning are in
        // TransformerElectricalSeeder (con_tx_hv, con_tx_lv, con_tx_earthing,
        // con_tx_aux, con_tx_ctrl, comm_tx_pretest, comm_tx_prot,
        // comm_tx_energise_ready, comm_tx_completion).
    }

    // ── Switchyard Mechanical Install (SUB.ELE.SY) ─────────
    // Companion to SUB.CIV.SY. Gated on the switchyard civil sign-off; each
    // equipment install follows the internal DAG defined in mechanical.md.
    // Structural Steel (seq 1) and Earth Mat (seq 2) fan out to the rest of
    // the equipment installs.

    private function seedSwitchyardMechanical(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_sub_ele_sy',
                'name' => 'Switchyard Mechanical Install',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_mech',
                'is_conditional' => true,
                'condition_flag' => 'substation_exists',
                'output_key' => 'sub_ele_sy_signoff',
                'output_name' => 'Switchyard Mechanical Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['sub_civ_sy_signoff', 'substation_equip_delivered', 'elec_p_ifc_sub_ifc_approved', 'elec_p_ifc_earth_approved'],
                'sort_order' => 108,
            ],
            [
                // 1. Structural steel — precedes everything that needs support
                ['name' => 'Structural Steel Erection', 'duration' => 8],
                // 2. Earth mat — follows steel, precedes equipment installs
                ['name' => 'Earth Mat and Earthing Down-Leads Installation', 'duration' => 5, 'predecessor_sequence' => 1],
                // 3–4. Post insulators → busbars chain
                ['name' => 'Post Insulator / Support Column Installation', 'duration' => 3, 'predecessor_sequence' => 2],
                ['name' => 'Busbar Installation', 'duration' => 6, 'predecessor_sequence' => 3],
                // 5–8. Equipment installs that fan out from earth-mat (seq 2)
                ['name' => 'Surge Arrester Installation', 'duration' => 2, 'predecessor_sequence' => 2],
                ['name' => 'Current Transformer Installation', 'duration' => 3, 'predecessor_sequence' => 2],
                ['name' => 'Voltage Transformer / CVT Installation', 'duration' => 3, 'predecessor_sequence' => 2],
                ['name' => 'Disconnector / Isolator Installation', 'duration' => 4, 'predecessor_sequence' => 2],
                // 9–10. Earth switch and CB follow disconnector
                ['name' => 'Earth Switch Installation', 'duration' => 2, 'predecessor_sequence' => 8],
                ['name' => 'Circuit Breaker Installation', 'duration' => 4, 'predecessor_sequence' => 8],
                // 11. Line Trap follows VT/CVT
                ['name' => 'Line Trap / Wave Trap Installation', 'duration' => 2, 'predecessor_sequence' => 7],
                // 12–13. Shunt Reactor and Cap Bank fan out directly from structural steel
                ['name' => 'Shunt Reactor Installation', 'duration' => 5, 'predecessor_sequence' => 1],
                ['name' => 'Capacitor Bank Installation', 'duration' => 5, 'predecessor_sequence' => 1],
                // 14. Roll-up milestone — sequenced after last activity; real fan-in
                // from all DAG leaves is handled by the package output + graph compiler.
                ['name' => 'Switchyard Mechanical Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Switchroom Installation (Substation) ───────────────

    private function seedSwitchroomInstallation(): void
    {
        // Switchroom Civil and Building Works moved to SubstationCivilSeeder
        // (SUB.CIV.SR). The `sr_civil_signoff` output_key is preserved there
        // so the downstream mechanical / electrical / P&C packages below
        // still resolve their civil gate.

        // Mechanical and Equipment Installation
        $this->createCustomPackage(
            [
                'code' => 'con_sr_mech',
                'name' => 'Switchroom Mechanical and Equipment Installation',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_mech',
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'sr_mech_signoff',
                'output_name' => 'Switchroom Mechanical Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['sr_civil_signoff', 'switchroom_equip_delivered', 'elec_p_ifc_earth_approved'],
                'sort_order' => 111,
            ],
            [
                // SUB.ELE.SR.010 — root of the containment/earthing chain
                ['name' => 'Cable Ladder, Tray and Containment Installation', 'duration' => 4],
                // SUB.ELE.SR.020–040 — fan out from containment
                ['name' => 'Earthing and Bonding Installation', 'duration' => 3, 'predecessor_sequence' => 1],
                ['name' => 'Lighting and Small Power Installation', 'duration' => 3, 'predecessor_sequence' => 1],
                ['name' => 'Fire Detection and Suppression Installation', 'duration' => 3, 'predecessor_sequence' => 1],
                // SUB.ELE.SR.050–060 — equipment installs follow earthing
                ['name' => 'Auxiliary Transformer Installation', 'duration' => 2, 'predecessor_sequence' => 2],
                ['name' => 'MV / LV Switchgear Installation', 'duration' => 5, 'predecessor_sequence' => 2],
                // SUB.ELE.SR.070–100 — AC aux → DC/battery → P&C → marshalling
                ['name' => 'AC Auxiliary Supply / ACDB Installation', 'duration' => 3, 'predecessor_sequence' => 6],
                ['name' => 'DC System and Battery Installation', 'duration' => 3, 'predecessor_sequence' => 7],
                ['name' => 'Protection and Control Panel Installation', 'duration' => 4, 'predecessor_sequence' => 6],
                ['name' => 'Marshalling Kiosk / LCC Installation', 'duration' => 2, 'predecessor_sequence' => 9],
                ['name' => 'Switchroom Mechanical Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Electrical Works
        $this->createCustomPackage(
            [
                'code' => 'con_sr_elec',
                'name' => 'Switchroom Electrical Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'substation',
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'sr_elec_signoff',
                'output_name' => 'Switchroom Electrical Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'sr_mech_signoff',
                'sort_order' => 112,
            ],
            [
                ['name' => 'MV Cables Incoming and Outgoing', 'duration' => 5],
                ['name' => 'LV Power Cabling', 'duration' => 3],
                ['name' => 'Control and Protection Cabling', 'duration' => 3],
                ['name' => 'SCADA Cabling', 'duration' => 2],
                ['name' => 'DC and UPS Cabling', 'duration' => 2],
                ['name' => 'Earthing Installation', 'duration' => 2],
                ['name' => 'Switchroom Electrical Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Protection and Control
        $this->createCustomPackage(
            [
                'code' => 'con_sr_pc',
                'name' => 'Switchroom Protection and Control',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalSecondary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'substation',
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'sr_pc_signoff',
                'output_name' => 'Switchroom P&C Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['sr_elec_signoff', 'elec_s_ifc_meter_approved'],
                'sort_order' => 113,
            ],
            [
                ['name' => 'MV Relay Wiring and Configuration', 'duration' => 3],
                ['name' => 'Busbar Protection Wiring', 'duration' => 2],
                ['name' => 'Interlock Wiring and Testing', 'duration' => 2],
                ['name' => 'SCADA and RTU Integration', 'duration' => 3],
                ['name' => 'DC and UPS Commissioning', 'duration' => 2],
                ['name' => 'HVAC and Fire Integration', 'duration' => 2],
                ['name' => 'Switchroom P&C Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Substation Construction ──────────────────────────────

    private function seedSubstationConstruction(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_substation',
                'name' => 'Substation Construction',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'substation',
                'is_conditional' => true,
                'condition_flag' => 'substation_exists',
                'output_key' => 'substation_construction_complete',
                'output_name' => 'Substation Construction Complete',
                'output_type' => OutputType::CompletionMilestone,
                // Substation Civil Works activity removed — that scope now lives
                // in SUB.CIV.SY (con_sub_civ_sy). This package gates on its output.
                'depends_on' => [
                    'substation_equip_delivered',
                    'design_freeze_approved',
                    'site_mob_complete',
                    'sub_civ_sy_signoff',
                ],
                'sort_order' => 120,
            ],
            [
                ['name' => 'Substation Equipment Installation', 'duration' => 10],
                ['name' => 'Substation Electrical Works', 'duration' => 8],
                ['name' => 'Substation Protection and Control', 'duration' => 5],
                ['name' => 'Substation Construction Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Control Room Installation (Substation) ─────────────

    private function seedControlRoomInstallation(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'con_control_room',
                'name' => 'Control Room Installation',
                'type' => PackageType::Construction,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'sub_mech',
                'is_conditional' => true,
                'condition_flag' => 'control_room_exists',
                'output_key' => 'control_room_construction_complete',
                'output_name' => 'Control Room Construction Complete',
                'output_type' => OutputType::CompletionMilestone,
                // Control Room civil works moved to SUB.CIV.CR. This package
                // now gates on that output and covers building / mech / elec.
                'depends_on' => [
                    'civil_ifc_cr_fdn_approved',
                    'site_mob_complete',
                    'cr_civil_signoff',
                    'hvac_ifc_cr_approved',
                    'elec_p_ifc_earth_approved',
                ],
                'sort_order' => 121,
            ],
            [
                // SUB.ELE.CR.010 — root of the containment/earthing chain
                ['name' => 'Cable Ladder, Tray and Containment Installation', 'duration' => 4],
                // SUB.ELE.CR.020–050 — fan out from containment
                ['name' => 'Earthing and Bonding Installation', 'duration' => 3, 'predecessor_sequence' => 1],
                ['name' => 'Lighting and Small Power Installation', 'duration' => 3, 'predecessor_sequence' => 1],
                ['name' => 'Fire Detection and Suppression Installation', 'duration' => 3, 'predecessor_sequence' => 1],
                ['name' => 'HVAC Equipment Installation', 'duration' => 4, 'predecessor_sequence' => 1],
                // SUB.ELE.CR.060 — Access floor follows earthing
                ['name' => 'Access Floor Installation', 'duration' => 3, 'predecessor_sequence' => 2],
                // SUB.ELE.CR.070–080 — AC aux → DC/battery
                ['name' => 'AC Auxiliary Supply / ACDB Installation', 'duration' => 3, 'predecessor_sequence' => 6],
                ['name' => 'DC System and Battery Installation', 'duration' => 3, 'predecessor_sequence' => 7],
                // SUB.ELE.CR.090 — P&C follows access floor
                ['name' => 'Protection and Control Panel Installation', 'duration' => 4, 'predecessor_sequence' => 6],
                // SUB.ELE.CR.100–110 — SCADA and comms follow P&C
                ['name' => 'SCADA / RTU Cabinet Installation', 'duration' => 4, 'predecessor_sequence' => 9],
                ['name' => 'Communications and Telecoms Rack Installation', 'duration' => 3, 'predecessor_sequence' => 9],
                // SUB.ELE.CR.120 — operator workstations follow SCADA
                ['name' => 'Operator Workstations and Mimic Panel Installation', 'duration' => 3, 'predecessor_sequence' => 10],
                // SUB.ELE.CR.130 — marshalling follows P&C
                ['name' => 'Marshalling Kiosk / Interface Cabinet Installation', 'duration' => 2, 'predecessor_sequence' => 9],
                ['name' => 'Control Room Construction Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // Topology activities (Battery, PCS, SUT) are generated programmatically
    // by TopologyActivityGenerator — see WBS.md for the full specification.

}