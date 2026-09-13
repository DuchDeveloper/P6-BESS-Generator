<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\DeliveryModel;
use App\Enums\ProjectStatus;
use App\Enums\WbsCategory;
use App\Models\Calendar;
use App\Models\Project;
use App\Models\WbsNode;

class ProjectSetupService
{
    /**
     * Create a new project with configuration and standard WBS skeleton.
     */
    public function createProject(array $config): Project
    {
        $calendar = Calendar::firstOrCreate(
            ['name' => $config['calendar_name'] ?? '5 Day Workweek'],
            [
                'workdays_per_week' => $config['workdays_per_week'] ?? 5,
                'working_days' => $config['working_days'] ?? [1, 2, 3, 4, 5],
                'is_default' => true,
            ]
        );

        $project = Project::create([
            'name' => $config['name'],
            'code' => $config['code'],
            'client' => $config['client'] ?? null,
            'description' => $config['description'] ?? null,
            'calendar_id' => $calendar->id,
            'start_date' => $config['start_date'],
            'delivery_model' => $config['delivery_model'] ?? DeliveryModel::Epc,
            'bess_free_issued' => $config['bess_free_issued'] ?? false,
            'switchroom_exists' => $config['switchroom_exists'] ?? true,
            'control_room_exists' => $config['control_room_exists'] ?? false,
            'transformer_exists' => $config['transformer_exists'] ?? true,
            'substation_exists' => $config['substation_exists'] ?? true,
            'scada_included' => $config['scada_included'] ?? true,
            'hvac_in_vendor_package' => $config['hvac_in_vendor_package'] ?? false,
            'fire_in_vendor_package' => $config['fire_in_vendor_package'] ?? false,
            // BOP scope flags
            'oam_building_exists' => $config['oam_building_exists'] ?? false,
            'guardhouse_exists' => $config['guardhouse_exists'] ?? false,
            'site_office_exists' => $config['site_office_exists'] ?? false,
            'ablutions_building_exists' => $config['ablutions_building_exists'] ?? false,
            'workshop_building_exists' => $config['workshop_building_exists'] ?? false,
            'fire_pump_house_exists' => $config['fire_pump_house_exists'] ?? false,
            'perimeter_fencing_exists' => $config['perimeter_fencing_exists'] ?? true,
            'access_roads_exists' => $config['access_roads_exists'] ?? true,
            'site_drainage_exists' => $config['site_drainage_exists'] ?? true,
            'potable_water_exists' => $config['potable_water_exists'] ?? false,
            'external_lighting_exists' => $config['external_lighting_exists'] ?? true,
            'cctv_security_exists' => $config['cctv_security_exists'] ?? false,
            'access_control_exists' => $config['access_control_exists'] ?? false,
            'public_address_exists' => $config['public_address_exists'] ?? false,
            'site_comms_backbone_exists' => $config['site_comms_backbone_exists'] ?? false,
            'site_ups_exists' => $config['site_ups_exists'] ?? true,
            'building_fire_system_exists' => $config['building_fire_system_exists'] ?? true,
            'lightning_protection_site_exists' => $config['lightning_protection_site_exists'] ?? false,
            'zone_count' => $config['zone_count'] ?? 1,
            'blocks_per_zone' => $config['blocks_per_zone'] ?? 1,
            'batteries_per_group' => $config['batteries_per_group'] ?? 4,
            'battery_groups_per_block' => $config['battery_groups_per_block'] ?? 5,
            'pcs_per_group' => $config['pcs_per_group'] ?? 2,
            'pcs_groups_per_block' => $config['pcs_groups_per_block'] ?? 5,
            'suts_per_block' => $config['suts_per_block'] ?? 2,
            'total_battery_count' => $config['total_battery_count'] ?? null,
            // Architecture and RMU
            'bess_architecture' => $config['bess_architecture'] ?? null,
            'dc_cable_type' => $config['dc_cable_type'] ?? null,
            'ac_cable_type' => $config['ac_cable_type'] ?? null,
            'mv_cable_type' => $config['mv_cable_type'] ?? null,
            'oem_product_name' => $config['oem_product_name'] ?? null,
            'container_unit_label' => $config['container_unit_label'] ?? null,
            'containers_per_sut' => $config['containers_per_sut'] ?? null,
            'pcs_factory_fitted' => $config['pcs_factory_fitted'] ?? true,
            'pcs_unit_type' => $config['pcs_unit_type'] ?? null,
            'batteries_per_pcs' => $config['batteries_per_pcs'] ?? null,
            'pcs_per_sut' => $config['pcs_per_sut'] ?? null,
            'pcs_output_voltage' => $config['pcs_output_voltage'] ?? null,
            'blocks_per_cluster_sut' => $config['blocks_per_cluster_sut'] ?? null,
            'rmu_topology' => $config['rmu_topology'] ?? 'none',
            'rmu_on_mv_ring' => $config['rmu_on_mv_ring'] ?? false,
            'rmu_mv_ring_voltage' => $config['rmu_mv_ring_voltage'] ?? null,
            'rmu_unit_type' => $config['rmu_unit_type'] ?? null,
            'status' => ProjectStatus::Draft,
        ]);

        $this->buildWbsSkeleton($project);

        return $project;
    }

    /**
     * Update project configuration. Re-evaluates conditional flags.
     */
    public function updateConfiguration(Project $project, array $config): Project
    {
        $project->update($config);

        return $project->fresh();
    }

    /**
     * Mark project as configured — ready for package instantiation.
     */
    public function markConfigured(Project $project): void
    {
        $project->update(['status' => ProjectStatus::Configured]);
    }

    /**
     * Build the standard six-branch WBS skeleton per CLAUDE.md Section 3.
     */
    private function buildWbsSkeleton(Project $project): void
    {
        $branches = [
            ['code' => '1', 'name' => 'Milestones', 'category' => WbsCategory::Milestones],
            ['code' => '2', 'name' => 'Project Management Plan', 'category' => WbsCategory::Management],
            ['code' => '3', 'name' => 'Design', 'category' => WbsCategory::Design],
            ['code' => '4', 'name' => 'Procurement', 'category' => WbsCategory::Procurement],
            ['code' => '5', 'name' => 'Construction', 'category' => WbsCategory::Construction],
            ['code' => '6', 'name' => 'Commissioning', 'category' => WbsCategory::Commissioning],
        ];

        // Root node
        $root = WbsNode::create([
            'project_id' => $project->id,
            'parent_id' => null,
            'code' => $project->code,
            'name' => $project->name,
            'level' => 0,
            'sort_order' => 0,
            'wbs_category' => null,
        ]);

        foreach ($branches as $i => $branch) {
            WbsNode::create([
                'project_id' => $project->id,
                'parent_id' => $root->id,
                'code' => $branch['code'],
                'name' => $branch['name'],
                'level' => 1,
                'sort_order' => $i + 1,
                'wbs_category' => $branch['category'],
            ]);
        }
    }

    /**
     * Get the WBS node for a given category, creating sub-nodes as needed.
     *
     * For Construction packages, the hierarchy is:
     *   Construction > Site Mobilisation > [package]
     *   Construction > Civil Works > Substation Civil > [package]
     *   Construction > Civil Works > BESS Civil > [package]
     *   Construction > Mechanical > Substation Mechanical > [package]
     *   etc.
     *
     * For other categories:
     *   Design > [discipline] > [package]
     */
    public function getOrCreateWbsNode(
        Project $project,
        WbsCategory $category,
        ?string $disciplineName = null,
        ?string $packageName = null,
        ?string $constructionWbsGroup = null,
    ): WbsNode {
        $branchNode = WbsNode::where('project_id', $project->id)
            ->where('level', 1)
            ->where('wbs_category', $category)
            ->firstOrFail();

        // Construction packages with a construction_wbs_group route through
        // the construction hierarchy regardless of whether a discipline was
        // supplied — sub_civ, sub_mech, bop_*, main_transformer etc. all have
        // groups that determine placement independent of discipline label.
        if ($category === WbsCategory::Construction && $constructionWbsGroup !== null) {
            return $this->getOrCreateConstructionWbsNode(
                $project, $branchNode, $disciplineName ?? '', $packageName, $constructionWbsGroup
            );
        }

        if ($disciplineName === null) {
            if ($packageName === null) {
                return $branchNode;
            }

            // No discipline grouping — package sits directly under the branch.
            return $this->ensureChildNode($project, $branchNode, $packageName, 2, $category);
        }

        // Standard hierarchy: Category > Discipline > Package
        $disciplineNode = $this->ensureChildNode($project, $branchNode, $disciplineName, 2, $category);

        if ($packageName === null) {
            return $disciplineNode;
        }

        return $this->ensureChildNode($project, $disciplineNode, $packageName, 3, $category);
    }

    /**
     * Construction WBS hierarchy:
     *   Site Mobilisation:  Construction > Site Mobilisation > [package]
     *   Substation Civil:   Construction > Civil Works > Substation Civil > [package]
     *   Main Transformer:   Construction > [discipline] > Main Transformer > [package]
     *   Substation (other): Construction > [discipline] > Substation [discipline] > [package]
     *   BESS:               Construction > [discipline] > BESS [discipline] > [package]
     *   BOP:                Construction > BOP > [BOP sub-group] > [package]
     *     (BOP is a top-level sibling of Civil Works / Mechanical / Electrical —
     *      it is NOT nested inside a discipline, per project spec.)
     */
    private function getOrCreateConstructionWbsNode(
        Project $project,
        WbsNode $constructionBranch,
        string $disciplineName,
        ?string $packageName,
        string $group,
    ): WbsNode {
        if ($group === 'site_mobilisation') {
            $mobNode = $this->ensureChildNode($project, $constructionBranch, 'Site Mobilisation', 2, WbsCategory::Construction, 1);
            if ($packageName === null) {
                return $mobNode;
            }
            return $this->ensureChildNode($project, $mobNode, $packageName, 3, WbsCategory::Construction);
        }

        // BOP packages are grouped under a single top-level BOP node regardless
        // of their underlying discipline (Civil, E&I, etc. all live together).
        if (str_starts_with($group, 'bop_')) {
            $bopNode = $this->ensureChildNode(
                $project, $constructionBranch, 'BOP', 2, WbsCategory::Construction
            );
            $bopSubLabel = match ($group) {
                'bop_buildings' => 'Buildings',
                'bop_site_civil' => 'Site Civil',
                'bop_site_ei' => 'Site E&I',
                'bop_safety' => 'Safety and Fire',
                default => 'BOP Other',
            };
            $subNode = $this->ensureChildNode(
                $project, $bopNode, $bopSubLabel, 3, WbsCategory::Construction
            );
            if ($packageName === null) {
                return $subNode;
            }
            return $this->ensureChildNode($project, $subNode, $packageName, 4, WbsCategory::Construction);
        }

        // Map discipline to construction discipline name
        $conDiscipline = $this->mapConstructionDiscipline($disciplineName);

        // Substation Civil packages (MT civil, Switchyard, Switchroom, Control Room)
        // all route to Construction > Civil Works > Substation Civil.
        if ($group === 'sub_civ') {
            $civilNode = $this->ensureChildNode(
                $project, $constructionBranch, 'Civil Works', 2, WbsCategory::Construction
            );
            $subCivilNode = $this->ensureChildNode(
                $project, $civilNode, 'Substation Civil', 3, WbsCategory::Construction
            );
            if ($packageName === null) {
                return $subCivilNode;
            }
            return $this->ensureChildNode(
                $project, $subCivilNode, $packageName, 4, WbsCategory::Construction
            );
        }

        // Substation Mechanical packages (MT mech, MT oil, Switchroom mech,
        // Control Room installation) all sit flat under a top-level Mechanical
        // WBS node, sibling to Civil Works.
        if ($group === 'sub_mech') {
            $mechNode = $this->ensureChildNode(
                $project, $constructionBranch, 'Mechanical', 2, WbsCategory::Construction
            );
            if ($packageName === null) {
                return $mechNode;
            }
            return $this->ensureChildNode(
                $project, $mechNode, $packageName, 3, WbsCategory::Construction
            );
        }

        // Level 2: discipline group (Civil Works, Mechanical, Electrical, etc.)
        $disciplineNode = $this->ensureChildNode(
            $project, $constructionBranch, $conDiscipline, 2, WbsCategory::Construction
        );

        // Level 3: Substation or BESS sub-group
        $groupLabel = match ($group) {
            'substation' => "Substation {$conDiscipline}",
            'main_transformer' => 'Main Transformer',
            'bess' => "BESS {$conDiscipline}",
            'bess_mechanical' => 'BESS Mechanical',
            'bess_electrical' => 'BESS Electrical Install',
            'bess_termination' => 'BESS Termination',
            default => $conDiscipline,
        };

        // For BESS topology groups, force them under the correct parent discipline
        if (in_array($group, ['bess_mechanical', 'bess_electrical', 'bess_termination'], true)) {
            $parentDiscipline = match ($group) {
                'bess_mechanical' => 'Mechanical',
                'bess_electrical' => 'Electrical Install',
                'bess_termination' => 'Termination',
            };
            $disciplineNode = $this->ensureChildNode(
                $project, $constructionBranch, $parentDiscipline, 2, WbsCategory::Construction
            );
        }

        $groupNode = $this->ensureChildNode(
            $project, $disciplineNode, $groupLabel, 3, WbsCategory::Construction
        );

        if ($packageName === null) {
            return $groupNode;
        }

        // Level 4: individual package
        return $this->ensureChildNode($project, $groupNode, $packageName, 4, WbsCategory::Construction);
    }

    /**
     * Map template discipline labels to construction WBS discipline names.
     */
    private function mapConstructionDiscipline(string $disciplineName): string
    {
        return match ($disciplineName) {
            'Civil' => 'Civil Works',
            'Electrical Primary', 'Electrical Secondary' => 'Electrical',
            'SCADA / Communications' => 'Electrical',
            // Discipline::None arrives as empty or literal 'None' — default
            // construction discipline for those is Mechanical.
            '', 'None' => 'Mechanical',
            default => $disciplineName,
        };
    }

    /**
     * Build WBS for topology-driven BESS packages.
     * Equipment type is the leaf WBS — groups are activities under it.
     *
     *   Construction > BESS Civil > Zone 1 > Block 1 > Battery Foundation
     *   Construction > BESS Civil > Zone 1 > Block 1 > PCS Foundation
     *   Construction > BESS Civil > Zone 1 > Block 1 > SUT Foundation
     *   Construction > BESS Mechanical > Zone 1 > Block 1 > Battery Installation
     *   etc.
     */
    public function getOrCreateTopologyWbsNode(
        Project $project,
        \App\Models\PackageTemplate $template,
        string $zoneLabel,
        string $blockLabel,
        string $groupName,
    ): WbsNode {
        $constructionBranch = WbsNode::where('project_id', $project->id)
            ->where('level', 1)
            ->where('wbs_category', WbsCategory::Construction)
            ->firstOrFail();

        // Map construction_wbs_group to discipline WBS name
        $bessWbsName = match ($template->construction_wbs_group) {
            'bess_civil' => 'BESS Civil',
            'bess_mechanical' => 'BESS Mechanical',
            'bess_electrical' => 'BESS Electrical Install',
            'bess_termination' => 'BESS Termination',
            default => 'BESS Mechanical',
        };

        // Map template code to equipment type sub-WBS name
        $equipmentWbs = match (true) {
            str_starts_with($template->code, 'con_bat_civil') => 'Battery Foundation',
            str_starts_with($template->code, 'con_pcs_civil') => 'PCS Foundation',
            str_starts_with($template->code, 'con_sut_civil') => 'SUT Foundation',
            str_starts_with($template->code, 'con_bat_mech') => 'Battery Installation',
            str_starts_with($template->code, 'con_pcs_mech') => 'PCS Installation',
            str_starts_with($template->code, 'con_sut_mech') => 'SUT Installation',
            str_starts_with($template->code, 'con_bat_elec') => 'Battery DC Cable Install',
            str_starts_with($template->code, 'con_pcs_elec') => 'PCS AC Cable Install',
            str_starts_with($template->code, 'con_sut_elec') => 'SUT MV Cable Install',
            str_starts_with($template->code, 'con_bat_term') => 'Battery DC Cable Termination',
            str_starts_with($template->code, 'con_pcs_term') => 'PCS AC Cable Termination',
            str_starts_with($template->code, 'con_sut_term') => 'SUT MV Cable Termination',
            default => $template->name,
        };

        // Level 2: BESS discipline (e.g., "BESS Civil")
        $bessNode = $this->ensureChildNode(
            $project, $constructionBranch, $bessWbsName, 2, WbsCategory::Construction
        );

        // Level 3: Zone
        $zoneNode = $this->ensureChildNode(
            $project, $bessNode, $zoneLabel, 3, WbsCategory::Construction
        );

        // Level 4: Block
        $blockNode = $this->ensureChildNode(
            $project, $zoneNode, $blockLabel, 4, WbsCategory::Construction
        );

        // Level 5: Equipment type (e.g., "Battery Foundation") — leaf WBS, activities live here
        return $this->ensureChildNode(
            $project, $blockNode, $equipmentWbs, 5, WbsCategory::Construction
        );
    }

    private function ensureChildNode(
        Project $project,
        WbsNode $parent,
        string $name,
        int $level,
        WbsCategory $category,
        ?int $forceSortOrder = null,
    ): WbsNode {
        return WbsNode::firstOrCreate(
            [
                'project_id' => $project->id,
                'parent_id' => $parent->id,
                'name' => $name,
            ],
            [
                'code' => $parent->code . '.' . ($parent->children()->count() + 1),
                'level' => $level,
                'sort_order' => $forceSortOrder ?? ($parent->children()->count() + 1),
                'wbs_category' => $category,
            ]
        );
    }
}