<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Discipline;
use App\Enums\OutputType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Database\Seeders\Traits\PackageSeederHelper;
use Illuminate\Database\Seeder;

class ProcurementSeeder extends Seeder
{
    use PackageSeederHelper;

    public function run(): void
    {
        $this->seedMainTransformerProcurement();
        $this->seedSwitchroomEquipmentProcurement();
        $this->seedBessProcurement();
        $this->seedSubstationEquipmentProcurement();
    }

    private function seedMainTransformerProcurement(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'proc_transformer',
                'name' => 'Main Transformer Procurement',
                'type' => PackageType::Procurement,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Procurement,
                'is_optional' => true,
                'is_conditional' => true,
                'condition_flag' => 'transformer_exists',
                'output_key' => 'transformer_delivered',
                'output_name' => 'Transformer Delivered',
                'output_type' => OutputType::DeliveryMilestone,
                'depends_on' => ['bod_concept_ga_approved', 'pm_procurement_strategy_approved'],
                'sort_order' => 90,
            ],
            [
                ['name' => 'Specification and Bid Evaluation', 'duration' => 10],
                ['name' => 'Purchase Order Award', 'duration' => 1, 'is_milestone' => true],
                ['name' => 'Manufacturing', 'duration' => 45],
                ['name' => 'Factory Acceptance Testing (FAT)', 'duration' => 4],
                ['name' => 'Shipping and Transport', 'duration' => 14],
                ['name' => 'Incoming Inspection', 'duration' => 1],
                ['name' => 'Transformer Delivered', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    private function seedSwitchroomEquipmentProcurement(): void
    {
        // MV Switchgear sub-chain
        $this->createCustomPackage(
            [
                'code' => 'proc_sr_mv_switchgear',
                'name' => 'MV Switchgear Procurement',
                'type' => PackageType::Procurement,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Procurement,
                'is_optional' => true,
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'mv_switchgear_fat_complete',
                'output_name' => 'MV Switchgear FAT Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['bod_concept_ga_approved', 'pm_procurement_strategy_approved'],
                'sort_order' => 91,
            ],
            [
                ['name' => 'MV Switchgear Specification and Bid', 'duration' => 10],
                ['name' => 'MV Switchgear Purchase Order Award', 'duration' => 1, 'is_milestone' => true],
                ['name' => 'MV Switchgear Manufacturing', 'duration' => 45],
                ['name' => 'MV Switchgear FAT', 'duration' => 4],
                ['name' => 'MV Switchgear FAT Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // LV Switchboard sub-chain
        $this->createCustomPackage(
            [
                'code' => 'proc_sr_lv_switchboard',
                'name' => 'LV Switchboard Procurement',
                'type' => PackageType::Procurement,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Procurement,
                'is_optional' => true,
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'lv_switchboard_fat_complete',
                'output_name' => 'LV Switchboard FAT Complete',
                'output_type' => OutputType::CompletionMilestone,
                'depends_on' => ['bod_concept_ga_approved', 'pm_procurement_strategy_approved'],
                'sort_order' => 92,
            ],
            [
                ['name' => 'LV Switchboard Specification and Bid', 'duration' => 10],
                ['name' => 'LV Switchboard Purchase Order Award', 'duration' => 1, 'is_milestone' => true],
                ['name' => 'LV Switchboard Manufacturing', 'duration' => 30],
                ['name' => 'LV Switchboard FAT', 'duration' => 3],
                ['name' => 'LV Switchboard FAT Complete', 'duration' => 0, 'is_milestone' => true],
            ]
        );

        // Combined shipping + delivery (depends on both FATs)
        $this->createCustomPackage(
            [
                'code' => 'proc_sr_equip_delivery',
                'name' => 'Switchroom Equipment Shipping and Delivery',
                'type' => PackageType::Procurement,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Procurement,
                'is_optional' => true,
                'is_conditional' => true,
                'condition_flag' => 'switchroom_exists',
                'output_key' => 'switchroom_equip_delivered',
                'output_name' => 'Switchroom Equipment Delivered',
                'output_type' => OutputType::DeliveryMilestone,
                'depends_on' => ['mv_switchgear_fat_complete', 'lv_switchboard_fat_complete'],
                'sort_order' => 93,
            ],
            [
                ['name' => 'Switchroom Equipment Shipping', 'duration' => 14],
                ['name' => 'Switchroom Equipment Incoming Inspection', 'duration' => 1],
                ['name' => 'Switchroom Equipment Delivered', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    private function seedBessProcurement(): void
    {
        // BESS Procurement — suppressed if BESS free-issued
        $this->createCustomPackage(
            [
                'code' => 'proc_bess',
                'name' => 'BESS Procurement',
                'type' => PackageType::Procurement,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Procurement,
                'is_optional' => true,
                'is_conditional' => true,
                'condition_flag' => 'bess_free_issued',
                'output_key' => 'bess_equipment_delivered',
                'output_name' => 'BESS Equipment Delivered',
                'output_type' => OutputType::DeliveryMilestone,
                'depends_on' => 'bod_concept_ga_approved',
                'sort_order' => 94,
            ],
            [
                ['name' => 'BESS Specification and Bid Evaluation', 'duration' => 15],
                ['name' => 'BESS Purchase Order Award', 'duration' => 1, 'is_milestone' => true],
                ['name' => 'BESS Manufacturing', 'duration' => 60],
                ['name' => 'BESS Factory Acceptance Testing', 'duration' => 5],
                ['name' => 'BESS Shipping and Transport', 'duration' => 21],
                ['name' => 'BESS Incoming Inspection', 'duration' => 2],
                ['name' => 'BESS Equipment Delivered', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }

    private function seedSubstationEquipmentProcurement(): void
    {
        $this->createCustomPackage(
            [
                'code' => 'proc_substation_equip',
                'name' => 'Substation Main Equipment Procurement',
                'type' => PackageType::Procurement,
                'discipline' => Discipline::None,
                'wbs_category' => WbsCategory::Procurement,
                'is_optional' => true,
                'is_conditional' => true,
                'condition_flag' => 'substation_exists',
                'output_key' => 'substation_equip_delivered',
                'output_name' => 'Substation Equipment Delivered',
                'output_type' => OutputType::DeliveryMilestone,
                'depends_on' => 'bod_concept_ga_approved',
                'sort_order' => 95,
            ],
            [
                ['name' => 'Substation Equipment Specification and Bid', 'duration' => 10],
                ['name' => 'Substation Equipment PO Award', 'duration' => 1, 'is_milestone' => true],
                ['name' => 'Substation Equipment Manufacturing', 'duration' => 50],
                ['name' => 'Substation Equipment FAT', 'duration' => 4],
                ['name' => 'Substation Equipment Shipping', 'duration' => 14],
                ['name' => 'Substation Equipment Incoming Inspection', 'duration' => 1],
                ['name' => 'Substation Equipment Delivered', 'duration' => 0, 'is_milestone' => true],
            ]
        );
    }
}