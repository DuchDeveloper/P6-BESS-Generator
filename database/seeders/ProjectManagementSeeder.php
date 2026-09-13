<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\OutputType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class ProjectManagementSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        // ── Project Kickoff ──────────────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_kickoff',
                'name' => 'Project Kickoff',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_kickoff_complete',
                'output_name' => 'Project Kickoff Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => 'ntp_received',
                'sort_order' => 1,
            ],
            [
                ['name' => 'Mobilise Project Team', 'duration' => 5],
                ['name' => 'Stakeholder Identification and Register', 'duration' => 5],
                ['name' => 'Kickoff Meeting', 'duration' => 1],
                ['name' => 'Issue Kickoff Meeting Minutes', 'duration' => 1],
                ['name' => 'Project Kickoff Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Project Management Plan (PMP) ────────────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_pmp',
                'name' => 'Project Management Plan',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_pmp_approved',
                'output_name' => 'PMP Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'pm_kickoff_complete',
                'sort_order' => 2,
            ],
            [
                ['name' => 'Draft Project Management Plan', 'duration' => 15],
                ['name' => 'Internal Review of PMP', 'duration' => 7],
                ['name' => 'Client Review and Comment', 'duration' => 5],
                ['name' => 'Incorporate Comments and Finalise', 'duration' => 3],
                ['name' => 'Issue PMP for Approval', 'duration' => 1],
                ['name' => 'PMP Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Schedule Management Plan ─────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_schedule_plan',
                'name' => 'Schedule Management Plan',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_schedule_plan_approved',
                'output_name' => 'Schedule Plan Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'pm_kickoff_complete',
                'sort_order' => 3,
            ],
            [
                ['name' => 'Develop WBS and Coding Structure', 'duration' => 10],
                ['name' => 'Develop Baseline Schedule', 'duration' => 20],
                ['name' => 'Define Schedule Reporting Requirements', 'duration' => 5],
                ['name' => 'Internal Review and Approval', 'duration' => 2],
                ['name' => 'Issue Schedule Management Plan', 'duration' => 1],
                ['name' => 'Schedule Plan Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Risk Management Plan and Register ────────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_risk_register',
                'name' => 'Risk Management Plan and Register',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_risk_register_approved',
                'output_name' => 'Risk Register Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'pm_kickoff_complete',
                'sort_order' => 4,
            ],
            [
                ['name' => 'Risk Identification Workshop', 'duration' => 2],
                ['name' => 'Develop Risk Register', 'duration' => 15],
                ['name' => 'Risk Quantification and Mitigation Strategies', 'duration' => 8],
                ['name' => 'Develop Risk Management Plan', 'duration' => 5],
                ['name' => 'Review and Approval', 'duration' => 2],
                ['name' => 'Risk Register Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Design Management Plan ───────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_design_mgmt_plan',
                'name' => 'Design Management Plan',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_design_mgmt_plan_approved',
                'output_name' => 'Design Management Plan Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'pm_pmp_approved',
                'sort_order' => 5,
            ],
            [
                ['name' => 'Define Design Deliverable Register', 'duration' => 15],
                ['name' => 'Establish Design Review and Approval Process', 'duration' => 8],
                ['name' => 'Define Interface Management Procedures', 'duration' => 5],
                ['name' => 'Draft Design Management Plan', 'duration' => 5],
                ['name' => 'Review and Approval', 'duration' => 2],
                ['name' => 'Design Management Plan Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Procurement Strategy and Plan ────────────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_procurement_strategy',
                'name' => 'Procurement Strategy and Plan',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_procurement_strategy_approved',
                'output_name' => 'Procurement Strategy Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'pm_pmp_approved',
                'sort_order' => 6,
            ],
            [
                ['name' => 'Identify Long-Lead Items', 'duration' => 20],
                ['name' => 'Develop Procurement Schedule', 'duration' => 10],
                ['name' => 'Define Vendor Qualification Criteria', 'duration' =>10],
                ['name' => 'Draft Procurement Strategy', 'duration' =>5],
                ['name' => 'Review and Approval', 'duration' => 2],
                ['name' => 'Procurement Strategy Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Health Safety and Environment Plan ───────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_hse_plan',
                'name' => 'HSE Management Plan',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_hse_plan_approved',
                'output_name' => 'HSE Plan Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'pm_kickoff_complete',
                'sort_order' => 7,
            ],
            [
                ['name' => 'Develop HSE Management Plan', 'duration' => 15],
                ['name' => 'Emergency Response Plan', 'duration' => 10],
                ['name' => 'Environmental Management Plan', 'duration' => 10],
                ['name' => 'Review and Approval', 'duration' => 5],
                ['name' => 'HSE Plan Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // ── Quality Management Plan ──────────────────────────
        $this->createCustomPackage(
            [
                'code' => 'pm_quality_plan',
                'name' => 'Quality Management Plan',
                'type' => PackageType::Management,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Management,
                'is_optional' => false,
                'output_key' => 'pm_quality_plan_approved',
                'output_name' => 'Quality Plan Approved',
                'output_type' => OutputType::ApprovalMilestone,
                'depends_on' => 'pm_pmp_approved',
                'sort_order' => 8,
            ],
            [
                ['name' => 'Define Quality Objectives and Standards', 'duration' => 15],
                ['name' => 'Develop ITP Framework', 'duration' => 8],
                ['name' => 'Draft Quality Management Plan', 'duration' => 8],
                ['name' => 'Review and Approval', 'duration' => 5],
                ['name' => 'Quality Plan Approved', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }
}
