<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\OutputType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class MilestonesSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Notice to Proceed (NTP) ──────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'ms_ntp',
                'name' => 'Notice to Proceed',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ntp_received',
                'output_name' => 'Notice to Proceed Received',
                'output_type' => OutputType::ApprovalMilestone,
                'sort_order' => 1,
            ],
            [
                ['name' => 'Notice to Proceed (NTP)', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Design Review Approvals ──────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'ms_30_design_review',
                'name' => '30% Design Review Approval',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_30_design_approved',
                'output_name' => '30% Design Review Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'dm_30_review_approved',
                'sort_order' => 2,
            ],
            [
                ['name' => '30% Design Review Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'ms_60_design_review',
                'name' => '60% Design Review Approval',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_60_design_approved',
                'output_name' => '60% Design Review Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'dm_60_review_approved',
                'sort_order' => 3,
            ],
            [
                ['name' => '60% Design Review Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'ms_90_design_review',
                'name' => '90% Design Review Approval',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_90_design_approved',
                'output_name' => '90% Design Review Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'dm_90_review_approved',
                'sort_order' => 4,
            ],
            [
                ['name' => '90% Design Review Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'ms_ifc_design_review',
                'name' => 'IFC Design Review Approval',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_ifc_design_approved',
                'output_name' => 'IFC Design Review Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'dm_ifc_review_approved',
                'sort_order' => 5,
            ],
            [
                ['name' => 'IFC Design Review Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Design Freeze ────────────────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'ms_design_freeze',
                'name' => 'Design Freeze',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_design_freeze_complete',
                'output_name' => 'Design Freeze Complete',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'design_freeze_approved',
                'sort_order' => 6,
            ],
            [
                ['name' => 'Design Freeze Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Procurement Award Milestones ─────────────────────
        $this->createCustomPackage(
            [
                'code' => 'ms_bess_po_award',
                'name' => 'BESS Purchase Order Award',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => true,
                'is_conditional' => true,
                'condition_flag' => 'bess_free_issued',
                'output_key' => 'ms_bess_po_awarded',
                'output_name' => 'BESS PO Awarded',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'bess_equipment_delivered',
                'sort_order' => 7,
            ],
            [
                ['name' => 'BESS Purchase Order Awarded', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'ms_transformer_po_award',
                'name' => 'Transformer Purchase Order Award',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'ms_transformer_po_awarded',
                'output_name' => 'Transformer PO Awarded',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'transformer_delivered',
                'sort_order' => 8,
            ],
            [
                ['name' => 'Transformer Purchase Order Awarded', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Construction Start ───────────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'ms_construction_start',
                'name' => 'Construction Start',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_construction_started',
                'output_name' => 'Construction Started',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'site_mob_complete',
                'sort_order' => 9,
            ],
            [
                ['name' => 'Construction Start', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Handover and Completion ──────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'ms_practical_completion',
                'name' => 'Practical Completion',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_practical_completion',
                'output_name' => 'Practical Completion Achieved',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'hold_point_1_released',
                'sort_order' => 10,
            ],
            [
                ['name' => 'Practical Completion', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        $this->createCustomPackage(
            [
                'code' => 'ms_final_handover',
                'name' => 'Final Handover',
                'type' => PackageType::Milestone,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Milestones,
                'is_optional' => false,
                'output_key' => 'ms_final_handover',
                'output_name' => 'Final Handover Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'ms_practical_completion',
                'sort_order' => 11,
            ],
            [
                ['name' => 'Final Handover', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }
}