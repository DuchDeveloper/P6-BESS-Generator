<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\OutputType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use App\Models\DependencyRule;
use App\Models\OutputDefinition;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

/**
 * Detailed transformer electrical works, testing and commissioning.
 *
 * Replaces the simplified con_tx_elec / con_tx_pc construction packages
 * and the simplified comm_tx_precomm / comm_tx commissioning packages
 * with the full activity breakdown from Transformer.md.
 *
 * WBS placement:
 *   Construction → Main Transformer → Electrical Works → {sub-section}
 *   Commissioning → Main Transformer → {sub-section}
 */
class TransformerElectricalSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Construction packages ───────────────────────────────
        $hvPkg   = $this->seedHvConnectionWorks();
        $lvPkg   = $this->seedLvConnectionWorks();
        $earthPkg = $this->seedEarthingAndProtection();
        $auxPkg  = $this->seedAuxiliaryElectrical();
        $ctrlPkg = $this->seedControlProtectionScada();

        // ── Intermediate outputs for cross-package dependencies ─
        $this->addCrossPackageOutputsAndDeps(
            $hvPkg, $lvPkg, $earthPkg, $auxPkg, $ctrlPkg
        );

        // ── Commissioning packages ──────────────────────────────
        $this->seedPreCommissioningTesting();
        $this->seedProtectionCommissioning();
        $this->seedEnergisationReadiness();
        $this->seedElectricalCompletion();
    }

    // ════════════════════════════════════════════════════════════
    //  CONSTRUCTION — HV Connection Works
    //  WBS: Construction → Main Transformer → Electrical Works → HV Connection Works
    // ════════════════════════════════════════════════════════════

    private function seedHvConnectionWorks(): \App\Models\PackageTemplate
    {
        return $this->createCustomPackage(
            [
                'code' => 'con_tx_hv',
                'name' => 'Transformer HV Connection Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'main_transformer',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_hv_signoff',
                'output_name' => 'Transformer HV Connections Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['tx_oil_complete', 'tx_mech_signoff'],
                'sort_order' => 103,
            ],
            [
                // MT-200
                ['name' => 'Install HV Cable Tray, Supports or Busduct Supports', 'duration' => 3],
                // MT-210
                ['name' => 'Supply and Install HV Busduct / Cable to Main Transformer', 'duration' => 5],
                // MT-220
                ['name' => 'Gland, Lug and Dress HV Cable', 'duration' => 2],
                // MT-230
                ['name' => 'Terminate and Joint HV Busduct / Cable', 'duration' => 3],
                // MT-240
                ['name' => 'Earth HV Cable Sheath / Screen', 'duration' => 1],
                // MT-250 — parallel with MT-240, both depend on MT-230 (seq 4)
                ['name' => 'Identify and Label HV Phases', 'duration' => 1, 'predecessor_sequence' => 4],
                // MT-260 — depends on MT-240 (seq 5) and MT-250 (seq 6)
                ['name' => 'Torque-Check and Sign Off HV Connections', 'duration' => 1],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════
    //  CONSTRUCTION — LV Connection Works
    //  WBS: Construction → Main Transformer → Electrical Works → LV Connection Works
    // ════════════════════════════════════════════════════════════

    private function seedLvConnectionWorks(): \App\Models\PackageTemplate
    {
        return $this->createCustomPackage(
            [
                'code' => 'con_tx_lv',
                'name' => 'Transformer LV Connection Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'main_transformer',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_lv_signoff',
                'output_name' => 'Transformer LV Connections Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['tx_oil_complete', 'tx_mech_signoff'],
                'sort_order' => 104,
            ],
            [
                // MT-300
                ['name' => 'Install LV Cable Tray, Supports or Busduct Supports', 'duration' => 3],
                // MT-310
                ['name' => 'Supply and Install LV Busduct / Cable to Main Transformer', 'duration' => 5],
                // MT-320
                ['name' => 'Gland, Lug and Dress LV Cable', 'duration' => 2],
                // MT-330
                ['name' => 'Terminate and Joint LV Busduct / Cable', 'duration' => 3],
                // MT-340
                ['name' => 'Connect LV Neutral', 'duration' => 1],
                // MT-350 — parallel with MT-340, both depend on MT-330 (seq 4)
                ['name' => 'Identify and Label LV Phases', 'duration' => 1, 'predecessor_sequence' => 4],
                // MT-360 — depends on MT-340 (seq 5) and MT-350 (seq 6)
                ['name' => 'Torque-Check and Sign Off LV Connections', 'duration' => 1],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════
    //  CONSTRUCTION — Earthing and Protection Equipment
    //  WBS: Construction → Main Transformer → Electrical Works → Earthing and Protection Equipment
    // ════════════════════════════════════════════════════════════

    private function seedEarthingAndProtection(): \App\Models\PackageTemplate
    {
        return $this->createCustomPackage(
            [
                'code' => 'con_tx_earthing',
                'name' => 'Transformer Earthing and Protection Equipment',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'main_transformer',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_earthing_signoff',
                'output_name' => 'Main Transformer Earthing and Bonding Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['site_mob_complete', 'tx_civil_signoff'],
                'sort_order' => 105,
            ],
            [
                // MT-400 — depends on site earthing (ext) + tx civil (ext)
                ['name' => 'Connect Transformer to Site Earthing System', 'duration' => 2],
                // MT-410
                ['name' => 'Install and Connect Neutral Earthing Resistor (NER)', 'duration' => 2],
                // MT-420 — depends on MT-210 (ext: tx_hv_busduct_installed)
                //          Cross-dep added in addCrossPackageOutputsAndDeps
                ['name' => 'Install and Connect Surge Arresters on HV Bushings', 'duration' => 2, 'predecessor_sequence' => 1],
                // MT-430 — depends on MT-400 (seq 1) + MT-420 (seq 3)
                ['name' => 'Connect Surge Arrester Earth Leads', 'duration' => 1, 'predecessor_sequence' => 3],
                // MT-440 — depends on MT-400 (seq 1)
                ['name' => 'Earth and Bond Transformer Tank', 'duration' => 1, 'predecessor_sequence' => 1],
                // MT-450 — depends on MT-410 (seq 2) + MT-400 (seq 1)
                ['name' => 'Connect Transformer Neutral Earthing', 'duration' => 1, 'predecessor_sequence' => 2],
                // MT-460 — depends on MT-410 (seq 2) + MT-640 (ext: tx_oltc_cables_installed)
                //          Cross-dep added in addCrossPackageOutputsAndDeps
                ['name' => 'Wire NER Control and Alarm Circuits', 'duration' => 1, 'predecessor_sequence' => 2],
                // MT-470 — depends on MT-430 (seq 4) + MT-440 (seq 5) + MT-450 (seq 6)
                ['name' => 'Test Earth Continuity', 'duration' => 1, 'predecessor_sequence' => 6],
                // MT-480 — depends on MT-460 (seq 7) + MT-470 (seq 8)
                ['name' => 'Sign Off Main Transformer Earthing and Bonding', 'duration' => 1, 'predecessor_sequence' => 8],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════
    //  CONSTRUCTION — Auxiliary Electrical Works
    //  WBS: Construction → Main Transformer → Electrical Works → Auxiliary Electrical Works
    // ════════════════════════════════════════════════════════════

    private function seedAuxiliaryElectrical(): \App\Models\PackageTemplate
    {
        return $this->createCustomPackage(
            [
                'code' => 'con_tx_aux',
                'name' => 'Transformer Auxiliary Electrical Works',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalPrimary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'main_transformer',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_aux_signoff',
                'output_name' => 'Transformer Auxiliary Electrical Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'tx_oil_complete',
                'sort_order' => 106,
            ],
            [
                // MT-500 — entry, depends on tx_oil_complete
                ['name' => 'Install and Wire Marshalling Kiosk', 'duration' => 3],
                // MT-510 — depends on MT-500 (seq 1)
                ['name' => 'Wire Cooler Fan / Pump Power and Control Circuits', 'duration' => 2],
                // MT-520 — depends on MT-500 (seq 1), parallel with MT-510
                ['name' => 'Wire Space Heater and Lighting Circuits', 'duration' => 1, 'predecessor_sequence' => 1],
                // MT-530 — depends on MT-500 (seq 1), parallel with MT-510/520
                ['name' => 'Wire OLTC Motor Supply and Control Circuits', 'duration' => 2, 'predecessor_sequence' => 1],
                // MT-540 — depends on MT-530 (seq 4), CONDITIONAL: oltc_with_avc
                ['name' => 'Wire RTCC / AVC Panel', 'duration' => 2, 'predecessor_sequence' => 4],
                // MT-550 — depends on MT-510 (seq 2), MT-520 (seq 3), MT-530 (seq 4), MT-540 (seq 5)
                ['name' => 'Connect Auxiliary AC Supply', 'duration' => 1, 'predecessor_sequence' => 5],
                // MT-560 — depends on MT-500 (seq 1) + MT-640 (ext: tx_oltc_cables_installed)
                //          Cross-dep added in addCrossPackageOutputsAndDeps
                ['name' => 'Connect DC Control Supply', 'duration' => 1, 'predecessor_sequence' => 1],
                // MT-570 — depends on MT-550 (seq 6) + MT-560 (seq 7)
                ['name' => 'Perform Auxiliary System Functional Checks', 'duration' => 2, 'predecessor_sequence' => 6],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════
    //  CONSTRUCTION — Control, Protection and SCADA Cabling
    //  WBS: Construction → Main Transformer → Electrical Works → Control, Protection and SCADA Cabling
    // ════════════════════════════════════════════════════════════

    private function seedControlProtectionScada(): \App\Models\PackageTemplate
    {
        return $this->createCustomPackage(
            [
                'code' => 'con_tx_ctrl',
                'name' => 'Transformer Control, Protection and SCADA Cabling',
                'type' => PackageType::Construction,
                'discipline' => Discipline::ElectricalSecondary,
                'wbs_category' => WbsCategory::Construction,
                'construction_wbs_group' => 'main_transformer',
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_ctrl_signoff',
                'output_name' => 'Control and Protection Cabling Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'tx_oil_complete',
                'sort_order' => 107,
            ],
            [
                // MT-600 — depends on MT-500 (ext: tx_marshalling_kiosk_installed)
                //          Cross-dep added in addCrossPackageOutputsAndDeps
                ['name' => 'Install and Wire Marshalling Kiosk Cables', 'duration' => 3],
                // MT-610 — depends on tx_oil_complete (already gated at pkg level)
                ['name' => 'Install Protection Relay Panel Control Cables', 'duration' => 3, 'predecessor_sequence' => null],
                // MT-620 — CONDITIONAL: ct_vt_in_scope
                ['name' => 'Install CT and VT Secondary Cables', 'duration' => 2, 'predecessor_sequence' => null],
                // MT-630 — depends on MT-500 (ext: tx_marshalling_kiosk_installed), same gate as seq 1
                ['name' => 'Install Buchholz, PRD, OTI and WTI Instrument Cables', 'duration' => 2, 'predecessor_sequence' => 1],
                // MT-640 — depends on MT-530 (ext: tx_oltc_motor_wired)
                //          Cross-dep added in addCrossPackageOutputsAndDeps
                ['name' => 'Install OLTC Control Cables', 'duration' => 2, 'predecessor_sequence' => null],
                // MT-650 — depends on MT-600 (seq 1) + MT-610 (seq 2)
                ['name' => 'Install SCADA and Telemetry Signal Cables', 'duration' => 3, 'predecessor_sequence' => 2],
                // MT-660 — depends on MT-500 (ext: tx_marshalling_kiosk_installed), same gate as seq 1
                ['name' => 'Install Auxiliary Power and Lighting Supply Cables', 'duration' => 2, 'predecessor_sequence' => 1],
                // MT-670 — depends on MT-630 (seq 4)
                ['name' => 'Wire Oil Level, Oil Temperature and Winding Temperature Alarms', 'duration' => 2, 'predecessor_sequence' => 4],
                // MT-680 — depends on ALL: seq 1-8
                ['name' => 'Ferrule and Label Control, Protection and SCADA Cables', 'duration' => 2, 'predecessor_sequence' => 8],
                // MT-690
                ['name' => 'Sign Off Control and Protection Cabling', 'duration' => 1],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════
    //  Cross-package intermediate outputs and dependency rules
    // ════════════════════════════════════════════════════════════

    private function addCrossPackageOutputsAndDeps(
        \App\Models\PackageTemplate $hvPkg,
        \App\Models\PackageTemplate $lvPkg,
        \App\Models\PackageTemplate $earthPkg,
        \App\Models\PackageTemplate $auxPkg,
        \App\Models\PackageTemplate $ctrlPkg,
    ): void {
        // ── HV seq 2 (MT-210) produces intermediate output ──────
        // Consumed by: Earthing seq 3 (MT-420)
        OutputDefinition::create([
            'package_template_id' => $hvPkg->id,
            'output_key' => 'tx_hv_busduct_installed',
            'output_name' => 'HV Busduct / Cable Installed',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 2,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $earthPkg->id,
            'required_output_key' => 'tx_hv_busduct_installed',
            'gates_activity_sequence' => 3, // MT-420
            'is_mandatory' => true,
        ]);

        // ── Auxiliary seq 1 (MT-500) produces intermediate output ─
        // Consumed by: Control seq 1 (MT-600), seq 4 (MT-630), seq 7 (MT-660)
        OutputDefinition::create([
            'package_template_id' => $auxPkg->id,
            'output_key' => 'tx_marshalling_kiosk_installed',
            'output_name' => 'Marshalling Kiosk Installed',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 1,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $ctrlPkg->id,
            'required_output_key' => 'tx_marshalling_kiosk_installed',
            'gates_activity_sequence' => 1, // MT-600
            'is_mandatory' => true,
        ]);

        // ── Auxiliary seq 4 (MT-530) produces intermediate output ─
        // Consumed by: Control seq 5 (MT-640)
        OutputDefinition::create([
            'package_template_id' => $auxPkg->id,
            'output_key' => 'tx_oltc_motor_wired',
            'output_name' => 'OLTC Motor Supply Wired',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 4,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $ctrlPkg->id,
            'required_output_key' => 'tx_oltc_motor_wired',
            'gates_activity_sequence' => 5, // MT-640
            'is_mandatory' => true,
        ]);

        // ── Control seq 5 (MT-640) produces intermediate output ──
        // Consumed by: Earthing seq 7 (MT-460), Auxiliary seq 7 (MT-560)
        OutputDefinition::create([
            'package_template_id' => $ctrlPkg->id,
            'output_key' => 'tx_oltc_cables_installed',
            'output_name' => 'OLTC Control Cables Installed',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 5,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $earthPkg->id,
            'required_output_key' => 'tx_oltc_cables_installed',
            'gates_activity_sequence' => 7, // MT-460
            'is_mandatory' => true,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $auxPkg->id,
            'required_output_key' => 'tx_oltc_cables_installed',
            'gates_activity_sequence' => 7, // MT-560
            'is_mandatory' => true,
        ]);

        // ── Control seq 4 (MT-630) produces intermediate output ──
        // Consumed by: Commissioning Protection (MT-940, MT-950, MT-960)
        OutputDefinition::create([
            'package_template_id' => $ctrlPkg->id,
            'output_key' => 'tx_instrument_cables_installed',
            'output_name' => 'Buchholz, PRD, OTI and WTI Instrument Cables Installed',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 4,
        ]);

        // ── Control seq 6 (MT-650) produces intermediate output ──
        // Consumed by: Commissioning Protection (MT-1020)
        OutputDefinition::create([
            'package_template_id' => $ctrlPkg->id,
            'output_key' => 'tx_scada_cables_installed',
            'output_name' => 'SCADA and Telemetry Signal Cables Installed',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 6,
        ]);

        // ── Control seq 3 (MT-620) produces intermediate output ──
        // Consumed by: Pre-Comm (MT-780), Protection Comm (MT-920, MT-930)
        // Conditional: ct_vt_in_scope
        OutputDefinition::create([
            'package_template_id' => $ctrlPkg->id,
            'output_key' => 'tx_ct_vt_cables_installed',
            'output_name' => 'CT and VT Secondary Cables Installed',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 3,
        ]);

        // ── Earthing seq 6 (MT-450) produces intermediate output ─
        // Consumed by: Pre-Comm (MT-790)
        OutputDefinition::create([
            'package_template_id' => $earthPkg->id,
            'output_key' => 'tx_neutral_earthing_connected',
            'output_name' => 'Transformer Neutral Earthing Connected',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 6,
        ]);

        // ── Earthing seq 4 (MT-430) produces intermediate output ─
        // Consumed by: Pre-Comm (MT-800)
        OutputDefinition::create([
            'package_template_id' => $earthPkg->id,
            'output_key' => 'tx_surge_arrester_earths_connected',
            'output_name' => 'Surge Arrester Earth Leads Connected',
            'output_type' => OutputType::CompletionMilestone,
            'produced_by_sequence' => 4,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  COMMISSIONING — Pre-Commissioning Testing
    //  WBS: Commissioning → Main Transformer → Pre-Commissioning Testing
    // ════════════════════════════════════════════════════════════

    private function seedPreCommissioningTesting(): void
    {
        $pkg = $this->createCustomPackage(
            [
                'code' => 'comm_tx_pretest',
                'name' => 'Transformer Pre-Commissioning Testing',
                'type' => PackageType::Commissioning,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_pretest_complete',
                'output_name' => 'Electrical Pre-Commissioning Test Report Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => [
                    'tx_hv_signoff',
                    'tx_lv_signoff',
                    'tx_ctrl_signoff',
                    'tx_earthing_signoff',
                    'itp_ifc_approved',
                ],
                'sort_order' => 142,
            ],
            [
                // MT-700 — depends on MT-260 (ext: tx_hv_signoff)
                ['name' => 'Test HV Cable Insulation Resistance', 'duration' => 1],
                // MT-710 — depends on MT-360 (ext: tx_lv_signoff), parallel with seq 1
                ['name' => 'Test LV Cable Insulation Resistance', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-720 — depends on MT-690 (ext: tx_ctrl_signoff)
                ['name' => 'Perform Point-to-Point Cable Continuity Checks', 'duration' => 2, 'predecessor_sequence' => null],
                // MT-730 — depends on MT-690 (ext: tx_ctrl_signoff)
                ['name' => 'Test Control Cable Insulation Resistance', 'duration' => 1, 'predecessor_sequence' => 3],
                // MT-740 — depends on MT-260 + MT-360 + MT-480 (all ext sign-offs)
                ['name' => 'Test Transformer Winding Insulation Resistance / PI', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-750
                ['name' => 'Test Transformer Winding Resistance', 'duration' => 1, 'predecessor_sequence' => 5],
                // MT-760
                ['name' => 'Perform Transformer Ratio Test', 'duration' => 1],
                // MT-770
                ['name' => 'Check Vector Group, Polarity and Phase Relationship', 'duration' => 1],
                // MT-780 — CONDITIONAL: ct_vt_in_scope, depends on MT-620 (ext: tx_ct_vt_cables_installed)
                ['name' => 'Test Bushing CT Ratio, Polarity and Knee Point', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-790 — depends on MT-450 (ext: tx_neutral_earthing_connected)
                ['name' => 'Test NER Resistance', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-800 — depends on MT-430 (ext: tx_surge_arrester_earths_connected)
                ['name' => 'Inspect and Test Surge Arresters', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-810 — CONDITIONAL: oil_filled_transformer, depends on tx_oil_complete
                ['name' => 'Review Transformer Oil Test Results', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-820 — depends on ALL above (seq 1-12)
                ['name' => 'Compile Electrical Pre-Commissioning Test Report', 'duration' => 2, 'predecessor_sequence' => 8],
            ]
        );

        // Additional dependency rules for specific activity gates
        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_ct_vt_cables_installed',
            'gates_activity_sequence' => 9, // MT-780
            'is_mandatory' => false, // conditional on ct_vt_in_scope
        ]);

        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_neutral_earthing_connected',
            'gates_activity_sequence' => 10, // MT-790
            'is_mandatory' => true,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_surge_arrester_earths_connected',
            'gates_activity_sequence' => 11, // MT-800
            'is_mandatory' => true,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_oil_complete',
            'gates_activity_sequence' => 12, // MT-810
            'is_mandatory' => false, // conditional on oil_filled_transformer
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  COMMISSIONING — Protection, Control and SCADA Commissioning
    //  WBS: Commissioning → Main Transformer → Protection, Control and SCADA Commissioning
    // ════════════════════════════════════════════════════════════

    private function seedProtectionCommissioning(): void
    {
        $pkg = $this->createCustomPackage(
            [
                'code' => 'comm_tx_prot',
                'name' => 'Transformer Protection, Control and SCADA Commissioning',
                'type' => PackageType::Commissioning,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'tx_prot_comm_signoff',
                'output_name' => 'Protection, Control and SCADA Commissioning Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => [
                    'elec_s_ifc_relay_approved',
                    'tx_ctrl_signoff',
                ],
                'sort_order' => 143,
            ],
            [
                // MT-900 — depends on MT-120 (ext: relay settings) + MT-690 (ext: tx_ctrl_signoff)
                ['name' => 'Upload Approved Protection Relay Settings', 'duration' => 1],
                // MT-910
                ['name' => 'Perform Protection Relay Secondary Injection Test', 'duration' => 2],
                // MT-920 — depends on MT-780 (ext: bushing CT test) + MT-910 (seq 2)
                ['name' => 'Test Transformer Differential Protection', 'duration' => 2],
                // MT-930 — parallel with MT-920, same deps
                ['name' => 'Test REF, Earth Fault and Overcurrent Protection', 'duration' => 2, 'predecessor_sequence' => 2],
                // MT-940 — depends on MT-670 (ext: instrument cables) + MT-910 (seq 2)
                ['name' => 'Test Buchholz Alarm and Trip Function', 'duration' => 1, 'predecessor_sequence' => 2],
                // MT-950 — parallel with MT-940
                ['name' => 'Test PRD Alarm and Trip Function', 'duration' => 1, 'predecessor_sequence' => 2],
                // MT-960 — parallel with MT-940
                ['name' => 'Test OTI / WTI Alarm and Trip Function', 'duration' => 1, 'predecessor_sequence' => 2],
                // MT-970 — depends on MT-570 (ext: tx_aux_signoff) + MT-640 (ext: tx_oltc_cables_installed)
                ['name' => 'Test OLTC Raise, Lower, Auto and Manual Functions', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-980
                ['name' => 'Test Trip Circuit Supervision', 'duration' => 1, 'predecessor_sequence' => 2],
                // MT-990 — depends on MT-920 (seq 3) + MT-930 (seq 4) + MT-980 (seq 9)
                ['name' => 'Test HV Breaker Trip / Close Interface', 'duration' => 1, 'predecessor_sequence' => 9],
                // MT-1000 — parallel with MT-990, same deps
                ['name' => 'Test LV Breaker Trip / Close Interface', 'duration' => 1, 'predecessor_sequence' => 9],
                // MT-1010 — depends on MT-970 (seq 8) + MT-990 (seq 10) + MT-1000 (seq 11)
                ['name' => 'Test Interlocks and Permissives', 'duration' => 1, 'predecessor_sequence' => 10],
                // MT-1020 — depends on MT-650 (ext: tx_scada_cables_installed) + MT-690 (ext: tx_ctrl_signoff)
                ['name' => 'Test SCADA Signal Point-to-Point', 'duration' => 2, 'predecessor_sequence' => null],
                // MT-1030 — depends on seq 5-8, 10-11, 13
                ['name' => 'Test SCADA Alarms and Status Indications', 'duration' => 2, 'predecessor_sequence' => 13],
                // MT-1040 — depends on ALL
                ['name' => 'Sign Off Protection, Control and SCADA Commissioning', 'duration' => 1],
            ]
        );

        // Additional dependency rules for specific activity gates

        // MT-920, MT-930 need tx_ct_vt_cables_installed (from MT-780 via pretest)
        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_pretest_complete',
            'gates_activity_sequence' => 3, // MT-920
            'is_mandatory' => true,
        ]);

        // MT-940, MT-950, MT-960 need instrument cables wired (MT-670)
        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_instrument_cables_installed',
            'gates_activity_sequence' => 5, // MT-940
            'is_mandatory' => true,
        ]);

        // MT-970 needs aux complete + OLTC cables
        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_aux_signoff',
            'gates_activity_sequence' => 8, // MT-970
            'is_mandatory' => true,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_oltc_cables_installed',
            'gates_activity_sequence' => 8, // MT-970
            'is_mandatory' => true,
        ]);

        // MT-1020 needs SCADA cables installed
        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_scada_cables_installed',
            'gates_activity_sequence' => 13, // MT-1020
            'is_mandatory' => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  COMMISSIONING — Energisation Readiness
    //  WBS: Commissioning → Main Transformer → Energisation Readiness
    // ════════════════════════════════════════════════════════════

    private function seedEnergisationReadiness(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'comm_tx_energise_ready',
                'name' => 'Transformer Energisation Readiness',
                'type' => PackageType::Commissioning,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'transformer_ready_to_energise',
                'output_name' => 'Ready-for-Energisation Certificate Issued',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['tx_pretest_complete', 'tx_prot_comm_signoff'],
                'sort_order' => 144,
            ],
            [
                // MT-1100 — depends on MT-820 (ext: tx_pretest_complete) + MT-1040 (ext: tx_prot_comm_signoff)
                ['name' => 'Close Out Electrical Punch List', 'duration' => 2],
                // MT-1110 — same deps as MT-1100, parallel
                ['name' => 'Review and Accept Test Certificates', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-1120 — depends on MT-1040 (ext: tx_prot_comm_signoff)
                ['name' => 'Confirm Protection Settings Approval', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-1130 — depends on MT-480 (ext: tx_earthing_signoff)
                ['name' => 'Confirm Earthing Sign-Off', 'duration' => 1, 'predecessor_sequence' => null],
                // MT-1140 — depends on MT-1110 (seq 2), MT-1120 (seq 3), MT-1130 (seq 4)
                ['name' => 'Approve Switching Program / Energisation Procedure', 'duration' => 2, 'predecessor_sequence' => 4],
                // MT-1150 — depends on MT-1100 (seq 1), MT-1110-1140 (seq 2-5)
                ['name' => 'Perform Pre-Energisation Electrical Inspection', 'duration' => 1, 'predecessor_sequence' => 5],
                // MT-1160
                ['name' => 'Issue Ready-for-Energisation Certificate', 'duration' => 1],
            ]
        );

        // Gate MT-1130 (seq 4) on earthing sign-off
        $pkg = \App\Models\PackageTemplate::where('code', 'comm_tx_energise_ready')->first();

        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_earthing_signoff',
            'gates_activity_sequence' => 4, // MT-1130
            'is_mandatory' => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  COMMISSIONING — Electrical Completion
    //  WBS: Commissioning → Main Transformer → Electrical Completion
    // ════════════════════════════════════════════════════════════

    private function seedElectricalCompletion(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'comm_tx_completion',
                'name' => 'Transformer Electrical Completion',
                'type' => PackageType::Commissioning,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'transformer_electrical_complete',
                'output_name' => 'Main Transformer Electrical Works Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'transformer_ready_to_energise',
                'sort_order' => 145,
            ],
            [
                // MT-1200 — depends on MT-1160 (ext: transformer_ready_to_energise)
                ['name' => 'Energise Main Transformer', 'duration' => 1],
                // MT-1210
                ['name' => 'Perform No-Load Voltage and Phase Rotation Check', 'duration' => 1],
                // MT-1220 — parallel with MT-1210
                ['name' => 'Observe Transformer Noise, Vibration and Temperature', 'duration' => 1, 'predecessor_sequence' => 1],
                // MT-1230 — depends on MT-1210 (seq 2) + MT-1220 (seq 3)
                ['name' => 'Perform Load Acceptance Check', 'duration' => 1, 'predecessor_sequence' => 3],
                // MT-1240
                ['name' => 'Check Post-Energisation SCADA and Protection Status', 'duration' => 1],
                // MT-1250
                ['name' => 'Mark Up As-Built Electrical Drawings', 'duration' => 2],
                // MT-1260 — depends on MT-820 (ext: tx_pretest_complete) + MT-1040 (ext: tx_prot_comm_signoff) + MT-1250 (seq 6)
                ['name' => 'Submit Final Electrical Test Dossier', 'duration' => 3],
                // MT-1270
                ['name' => 'Issue Electrical Works Completion Certificate', 'duration' => 1],
                // MT-1280 — milestone
                ['name' => 'Main Transformer Electrical Works Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Gate MT-1260 (seq 7) on pretest and protection commissioning reports
        $pkg = \App\Models\PackageTemplate::where('code', 'comm_tx_completion')->first();

        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_pretest_complete',
            'gates_activity_sequence' => 7, // MT-1260
            'is_mandatory' => true,
        ]);

        DependencyRule::create([
            'consumer_template_id' => $pkg->id,
            'required_output_key' => 'tx_prot_comm_signoff',
            'gates_activity_sequence' => 7, // MT-1260
            'is_mandatory' => true,
        ]);
    }
}