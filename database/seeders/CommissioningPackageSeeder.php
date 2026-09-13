<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\OutputType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class CommissioningPackageSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        $this->seedSwitchroomPreCommissioning();
        $this->seedSwitchroomCommissioning();
        // Transformer commissioning packages (pre-commissioning testing,
        // protection commissioning, energisation readiness, electrical completion)
        // are in TransformerElectricalSeeder — called separately by PackageLibrarySeeder.
        $this->seedEnergisationMilestones();
    }

    // ── Switchroom Pre-Commissioning ─────────────────────────

    private function seedSwitchroomPreCommissioning(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'comm_sr_precomm',
                'name' => 'Switchroom Pre-Commissioning',
                'type' => PackageType::Commissioning,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'sr_precomm_signoff',
                'output_name' => 'Switchroom Pre-Commissioning Sign-Off',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['sr_pc_signoff', 'itp_ifc_approved'],
                'sort_order' => 140,
            ],
            [
                ['name' => 'MV Insulation Resistance and HV Tests', 'duration' => 2],
                ['name' => 'MV Cable Testing', 'duration' => 2],
                ['name' => 'LV Circuit Testing', 'duration' => 2],
                ['name' => 'CT and VT Ratio Testing', 'duration' => 1],
                ['name' => 'Earthing Continuity Test', 'duration' => 1],
                ['name' => 'DC and UPS Function Test', 'duration' => 1],
                ['name' => 'Protection Relay Injection Testing', 'duration' => 3],
                ['name' => 'Interlock Testing', 'duration' => 2],
                ['name' => 'SCADA Loop Test', 'duration' => 2],
                ['name' => 'HVAC and Fire Function Test', 'duration' => 1],
                ['name' => 'Punch List Closeout and Sign-Off', 'duration' => 2],
                ['name' => 'Switchroom Pre-Commissioning Sign-Off', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Switchroom Commissioning ─────────────────────────────

    private function seedSwitchroomCommissioning(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'comm_sr',
                'name' => 'Switchroom Commissioning',
                'type' => PackageType::Commissioning,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'sr_comm_handover',
                'output_name' => 'Switchroom As-Built and Handover',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'sr_precomm_signoff',
                'sort_order' => 141,
            ],
            [
                ['name' => 'Commissioning Plan and PTW', 'duration' => 1],
                ['name' => 'Safety Clearance', 'duration' => 1],
                ['name' => 'First Energisation MV Incomer', 'duration' => 1],
                ['name' => 'Phase Rotation Check', 'duration' => 1],
                ['name' => 'MV Feeder Energisation', 'duration' => 1],
                ['name' => 'Live Protection Tests', 'duration' => 2],
                ['name' => 'SCADA Live Verification', 'duration' => 2],
                ['name' => 'HVAC and Fire Live Commissioning', 'duration' => 1],
                ['name' => 'Punch List Closeout', 'duration' => 2],
                ['name' => 'Switchroom As-Built and Handover', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    // ── Energisation Milestones ──────────────────────────────

    private function seedEnergisationMilestones(): void
    {
        // Substation Ready to Energise
        $this->createCustomPackage(
            [
                'code' => 'ms_substation_ready',
                'name' => 'Substation Ready to Energise',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'substation_exists',
                'output_key' => 'substation_ready_to_energise',
                'output_name' => 'Substation Ready to Energise',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'substation_construction_complete',
                'sort_order' => 150,
            ],
            [
                ['name' => 'Substation Ready to Energise', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Substation Energised
        $this->createCustomPackage(
            [
                'code' => 'ms_substation_energised',
                'name' => 'Substation Energised',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'substation_exists',
                'output_key' => 'substation_energised',
                'output_name' => 'Substation Energised',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'substation_ready_to_energise',
                'sort_order' => 151,
            ],
            [
                ['name' => 'Substation Energised', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Switchroom Ready to Energise
        $this->createCustomPackage(
            [
                'code' => 'ms_sr_ready',
                'name' => 'Switchroom Ready to Energise',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'switchroom_ready_to_energise',
                'output_name' => 'Switchroom Ready to Energise',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'sr_precomm_signoff',
                'sort_order' => 152,
            ],
            [
                ['name' => 'Switchroom Ready to Energise', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Switchroom Energised
        $this->createCustomPackage(
            [
                'code' => 'ms_sr_energised',
                'name' => 'Switchroom Energised',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'switchroom_energised',
                'output_name' => 'Switchroom Energised',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['switchroom_ready_to_energise', 'substation_energised'],
                'sort_order' => 153,
            ],
            [
                ['name' => 'Switchroom Energised', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Auxiliary Power Available
        $this->createCustomPackage(
            [
                'code' => 'ms_aux_power',
                'name' => 'Auxiliary Power Available',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'output_key' => 'aux_power_available',
                'output_name' => 'Auxiliary Power Available',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'switchroom_energised',
                'sort_order' => 154,
            ],
            [
                ['name' => 'Auxiliary Power Available', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // First BESS Energisation
        $this->createCustomPackage(
            [
                'code' => 'ms_first_bess_energise',
                'name' => 'First BESS Energisation',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'output_key' => 'first_bess_energisation',
                'output_name' => 'First BESS Energisation',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'aux_power_available',
                'sort_order' => 155,
            ],
            [
                ['name' => 'First BESS Energisation', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // BESS Ready for Online Commissioning
        $this->createCustomPackage(
            [
                'code' => 'ms_bess_online_comm',
                'name' => 'BESS Ready for Online Commissioning',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'output_key' => 'bess_ready_online_comm',
                'output_name' => 'BESS Ready for Online Commissioning',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'first_bess_energisation',
                'sort_order' => 156,
            ],
            [
                ['name' => 'BESS Ready for Online Commissioning', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Hold Point 1 Released
        $this->createCustomPackage(
            [
                'code' => 'ms_hold_point_1',
                'name' => 'Hold Point 1 Released',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Commissioning,
                'output_key' => 'hold_point_1_released',
                'output_name' => 'Hold Point 1 Released',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'bess_ready_online_comm',
                'sort_order' => 157,
            ],
            [
                ['name' => 'Hold Point 1 Released', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }
}