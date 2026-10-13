<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\BessArchitecture;
use App\Enums\ConstraintType;
use App\Enums\EquipmentNodeType;
use App\Enums\InterfaceType;
use App\Enums\OwnershipMode;
use App\Enums\P6TaskType;
use App\Enums\ProjectStatus;
use App\Enums\RelationshipOrigin;
use App\Enums\RelationshipType;
use App\Enums\ResolutionType;
use App\Enums\WbsCategory;
use App\Models\ActivityInstance;
use App\Models\EquipmentNode;
use App\Models\InterfaceRecord;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Models\ProviderResolution;
use App\Models\Relationship;
use App\Models\WbsNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GraphCompilerService
{
    /** Activity code of the single terminal milestone every phase closure rolls up to. */
    public const PROJECT_COMPLETE_CODE = 'PROJECT-COMPLETE';

    public function __construct(
        private readonly ProjectSetupService $setupService,
        private readonly TopologyService $topologyService,
        private readonly TopologyActivityGenerator $topologyActivityGenerator,
        private readonly DependencyResolverService $dependencyResolver,
    ) {}

    /**
     * Compile the full schedule graph for a project.
     * Pipeline: instantiate activities → build internal relationships → generate boundary
     *           milestones → build cross-package relationships → generate topology equipment
     *           and activities → generate interface records → generate roll-up closures
     *           → set start constraints.
     *
     * Runs in one transaction, so a failure leaves the previous compilation intact.
     */
    public function compile(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            // Clear previous compilation
            $this->clearCompilation($project);

            $activePackages = PackageInstance::where('project_id', $project->id)
                ->where('selected', true)
                ->where('applicable', true)
                ->with(['template.activityTemplates', 'template.outputDefinitions', 'template.dependencyRules'])
                ->orderBy('id')
                ->get();

            // Stage 1: Instantiate activities from templates
            $this->instantiateActivities($project, $activePackages);

            // Stage 2: Build internal relationships (within packages)
            $this->buildInternalRelationships($project, $activePackages);

            // Stage 2b: Fan in DAG leaves into each package's completion milestone
            $this->fanInPackageLeavesToCompletionMilestone($project, $activePackages);

            // Stage 3: Generate boundary milestones for external packages. Runs
            // before Stage 4 so the receipt milestones exist to be linked.
            $this->generateBoundaryMilestones($project, $activePackages);

            // Stage 4: Build cross-package dependency relationships
            $this->buildDependencyRelationships($project, $activePackages);

            // Stage 5: Generate equipment topology nodes
            $this->generateEquipmentTopology($project);

            // Stage 6: Generate topology activities (Battery, PCS, SUT per WBS.md)
            $archConfig = $this->buildArchitectureConfig($project);
            $this->topologyActivityGenerator->generate($project, $archConfig);

            // Stage 7: Generate interface records
            $this->generateInterfaceActivities($project);

            // Stage 8: Roll dangling milestones up to phase closures
            $this->generateRollupClosures($project);

            // Stage 9: Set project start constraints
            $this->setProjectStartConstraint($project);

            $project->update([
                'status' => ProjectStatus::Compiled,
                'compiled_at' => now(),
            ]);
        });
    }

    /**
     * Stage 1: Create ActivityInstance records from ActivityTemplates for each active internal package.
     */
    private function instantiateActivities(Project $project, $activePackages): void
    {
        $activityCodeCounter = 1;

        foreach ($activePackages as $instance) {
            // External packages don't get activities — they get boundary milestones in Stage 3
            if ($instance->ownership_mode === OwnershipMode::External) {
                continue;
            }

            // NotApplicable and IncludedElsewhere skip activity generation
            if (!$instance->ownership_mode->generatesActivities()) {
                continue;
            }

            foreach ($instance->template->activityTemplates as $actTemplate) {
                $codePrefix = $this->buildActivityCodePrefix($instance);

                ActivityInstance::create([
                    'project_id' => $project->id,
                    'package_instance_id' => $instance->id,
                    'wbs_node_id' => $instance->wbs_node_id,
                    'activity_code' => sprintf('%s-%03d', $codePrefix, $actTemplate->sequence),
                    'name' => $this->buildActivityName($instance, $actTemplate->name),
                    'duration_days' => $actTemplate->is_milestone ? 0 : $actTemplate->duration_days,
                    'is_milestone' => $actTemplate->is_milestone,
                    'sequence' => $actTemplate->sequence,
                    'p6_task_type' => $actTemplate->p6_task_type,
                ]);
            }

            $activityCodeCounter++;
        }
    }

    /**
     * Stage 2: Build FS relationships within each package (activity N → activity N+1).
     */
    private function buildInternalRelationships(Project $project, $activePackages): void
    {
        foreach ($activePackages as $instance) {
            if (!$instance->ownership_mode->generatesActivities()) {
                continue;
            }

            $activities = ActivityInstance::where('package_instance_id', $instance->id)
                ->orderBy('sequence')
                ->get();

            foreach ($activities as $activity) {
                // Find predecessor within same package by sequence reference
                $predSequence = $instance->template->activityTemplates
                    ->firstWhere('sequence', $activity->sequence)
                    ?->predecessor_sequence;

                if ($predSequence === null) {
                    continue;
                }

                $predecessor = $activities->firstWhere('sequence', $predSequence);
                if ($predecessor === null) {
                    continue;
                }

                Relationship::create([
                    'project_id' => $project->id,
                    'predecessor_id' => $predecessor->id,
                    'successor_id' => $activity->id,
                    'relationship_type' => RelationshipType::FinishToStart,
                    'lag_days' => 0,
                    'origin' => RelationshipOrigin::Internal,
                ]);
            }
        }
    }

    /**
     * Stage 2b: For each package, link every DAG leaf (activity with no successor
     * in the same package) into the package's completion anchor.
     *
     * The anchor is the package's milestone activity if one exists; otherwise the
     * activity referenced by the output definition's `produced_by_sequence`
     * (i.e. the package's designated sign-off activity).
     *
     * Packages whose work is a parallel DAG use single-valued
     * `predecessor_sequence`, which can only chain one path into the anchor.
     * This stage fans the remaining leaves in.
     */
    private function fanInPackageLeavesToCompletionMilestone(Project $project, $activePackages): void
    {
        foreach ($activePackages as $instance) {
            if (!$instance->ownership_mode->generatesActivities()) {
                continue;
            }

            $activities = ActivityInstance::where('package_instance_id', $instance->id)->get();
            if ($activities->isEmpty()) {
                continue;
            }

            $anchor = $this->completionAnchor($instance, $activities);

            if ($anchor === null) {
                continue;
            }

            $candidateLeaves = $activities->where('id', '!==', $anchor->id);
            if ($candidateLeaves->isEmpty()) {
                continue;
            }

            $idsWithSuccessors = Relationship::whereIn('predecessor_id', $candidateLeaves->pluck('id'))
                ->pluck('predecessor_id')
                ->unique();

            $leafActivities = $candidateLeaves->whereNotIn('id', $idsWithSuccessors);

            foreach ($leafActivities as $leaf) {
                if ($leaf->id === $anchor->id) {
                    continue;
                }
                $exists = Relationship::where('predecessor_id', $leaf->id)
                    ->where('successor_id', $anchor->id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                Relationship::create([
                    'project_id' => $project->id,
                    'predecessor_id' => $leaf->id,
                    'successor_id' => $anchor->id,
                    'relationship_type' => RelationshipType::FinishToStart,
                    'lag_days' => 0,
                    'origin' => RelationshipOrigin::Internal,
                ]);
            }
        }
    }

    /**
     * Stage 4: Build cross-package relationships from ProviderResolutions.
     * Connects provider output activity → consumer gated activity, for both
     * static dependency rules and the dynamic predecessors of design
     * management packages (CLAUDE.md §7.9).
     *
     * External packages take part through their boundary receipt milestones:
     * a provider feeds the receipt, and the receipt feeds downstream consumers.
     */
    private function buildDependencyRelationships(Project $project, Collection $activePackages): void
    {
        $resolutions = ProviderResolution::where('project_id', $project->id)
            ->where('resolution_type', '!=', ResolutionType::Unresolved)
            ->get()
            ->keyBy('output_key');

        $activities = ActivityInstance::where('project_id', $project->id)->get();
        $activitiesById = $activities->keyBy('id');
        $activitiesByPackage = $activities->groupBy('package_instance_id');
        $packagesById = $activePackages->keyBy('id');
        $dynamicDeps = $this->dependencyResolver->gatherDynamicPredecessors($activePackages);

        $linked = Relationship::where('project_id', $project->id)
            ->get(['predecessor_id', 'successor_id'])
            ->mapWithKeys(fn (Relationship $r) => ["{$r->predecessor_id}:{$r->successor_id}" => true])
            ->all();

        foreach ($activePackages as $consumer) {
            /** @var Collection<int, ActivityInstance> $consumerActivities */
            $consumerActivities = $activitiesByPackage->get($consumer->id, collect());
            if ($consumerActivities->isEmpty()) {
                continue;
            }

            // output_key → the consumer activity sequence it gates
            $gates = [];
            foreach ($consumer->template->dependencyRules as $rule) {
                $gates[$rule->required_output_key] ??= $rule->gates_activity_sequence ?? 1;
            }
            foreach ($dynamicDeps[$consumer->id] ?? [] as $outputKey) {
                $gates[$outputKey] ??= 1;
            }

            foreach ($gates as $outputKey => $gatedSequence) {
                $resolution = $resolutions->get($outputKey);
                if ($resolution === null) {
                    continue;
                }

                $providerActivity = $this->findProviderActivity($resolution, $activitiesById, $activitiesByPackage, $packagesById);
                if ($providerActivity === null) {
                    continue;
                }

                $targets = $consumer->ownership_mode === OwnershipMode::External
                    ? $consumerActivities
                    : $consumerActivities->where('sequence', $gatedSequence)->take(1);

                foreach ($targets as $consumerActivity) {
                    // Never link a package to itself (e.g. an output resolved
                    // through the package that contains the consumer).
                    if ($providerActivity->package_instance_id === $consumerActivity->package_instance_id) {
                        continue;
                    }

                    $key = "{$providerActivity->id}:{$consumerActivity->id}";
                    if (isset($linked[$key])) {
                        continue;
                    }

                    Relationship::create([
                        'project_id' => $project->id,
                        'predecessor_id' => $providerActivity->id,
                        'successor_id' => $consumerActivity->id,
                        'relationship_type' => RelationshipType::FinishToStart,
                        'lag_days' => 0,
                        'origin' => RelationshipOrigin::Dependency,
                    ]);
                    $linked[$key] = true;
                }
            }
        }
    }

    /**
     * Stage 3: Generate boundary milestones for external packages.
     * External packages get a receipt milestone per output instead of full activities.
     */
    private function generateBoundaryMilestones(Project $project, $activePackages): void
    {
        $externalPackages = $activePackages->filter(
            fn (PackageInstance $inst) => $inst->ownership_mode === OwnershipMode::External
        );

        foreach ($externalPackages as $instance) {
            foreach ($instance->template->outputDefinitions->values() as $index => $output) {
                $codePrefix = $this->buildActivityCodePrefix($instance);

                $milestone = ActivityInstance::create([
                    'project_id' => $project->id,
                    'package_instance_id' => $instance->id,
                    'wbs_node_id' => $instance->wbs_node_id,
                    // P6 needs unique activity IDs: number the second and later receipts.
                    'activity_code' => $index === 0 ? sprintf('%s-BM', $codePrefix) : sprintf('%s-BM%d', $codePrefix, $index + 1),
                    'name' => $output->output_name . ' (External Receipt)',
                    'duration_days' => 0,
                    'is_milestone' => true,
                    'sequence' => 1,
                    'p6_task_type' => P6TaskType::FinishMilestone,
                ]);

                // Update provider resolution to point to this boundary milestone
                ProviderResolution::where('project_id', $project->id)
                    ->where('output_key', $output->output_key)
                    ->update(['provider_activity_instance_id' => $milestone->id]);
            }
        }
    }

    /**
     * Stage 5: Generate equipment topology nodes from per-block configuration.
     * Delegates to TopologyService which reads zone_configurations/block_configurations.
     * Falls back to flat project fields if no block configs exist.
     */
    private function generateEquipmentTopology(Project $project): void
    {
        $hasBlockConfigs = $project->blockConfigurations()->exists();

        if ($hasBlockConfigs) {
            $this->topologyService->generateEquipmentNodes($project);
        } else {
            // Fallback: initialize from project defaults, then generate
            $this->topologyService->initializeFromDefaults($project);
            $this->topologyService->generateEquipmentNodes($project);
        }
    }

    /**
     * Stage 7: Generate interface records between equipment nodes.
     * Battery Group N → PCS Group N (DC), PCS → SUT (LV AC), SUT → Switchroom (MV).
     */
    private function generateInterfaceActivities(Project $project): void
    {
        InterfaceRecord::where('project_id', $project->id)->delete();

        $nodes = EquipmentNode::where('project_id', $project->id)->get();
        $switchroom = $nodes->firstWhere('type', EquipmentNodeType::Switchroom);
        $transformer = $nodes->firstWhere('type', EquipmentNodeType::Transformer);
        $substation = $nodes->firstWhere('type', EquipmentNodeType::Substation);

        for ($zone = 1; $zone <= $project->zone_count; $zone++) {
            for ($block = 1; $block <= $project->blocks_per_zone; $block++) {
                $batteryGroups = $nodes->filter(fn ($n) =>
                    $n->type === EquipmentNodeType::BatteryGroup &&
                    $n->zone_number === $zone &&
                    $n->block_number === $block
                )->sortBy('group_number');

                $pcsGroups = $nodes->filter(fn ($n) =>
                    $n->type === EquipmentNodeType::PcsGroup &&
                    $n->zone_number === $zone &&
                    $n->block_number === $block
                )->sortBy('group_number');

                $suts = $nodes->filter(fn ($n) =>
                    $n->type === EquipmentNodeType::Sut &&
                    $n->zone_number === $zone &&
                    $n->block_number === $block
                )->sortBy('group_number');

                // Battery Group N → PCS Group N (DC cables)
                foreach ($batteryGroups as $bg) {
                    $pcs = $pcsGroups->firstWhere('group_number', $bg->group_number);
                    if ($pcs) {
                        InterfaceRecord::create([
                            'project_id' => $project->id,
                            'from_node_id' => $bg->id,
                            'to_node_id' => $pcs->id,
                            'interface_type' => InterfaceType::Power,
                            'cable_type' => 'DC',
                            'generates_activities' => true,
                            'activity_types' => ['cable_install', 'cable_termination', 'point_to_point'],
                        ]);
                    }
                }

                // No-SUT architectures: PCS feeds the switchroom directly at MV.
                if ($suts->isEmpty()) {
                    foreach ($switchroom !== null ? $pcsGroups : [] as $pcs) {
                        InterfaceRecord::create([
                            'project_id' => $project->id,
                            'from_node_id' => $pcs->id,
                            'to_node_id' => $switchroom->id,
                            'interface_type' => InterfaceType::Power,
                            'cable_type' => 'MV',
                            'generates_activities' => false, // MV cable activities come from the topology generator
                            'activity_types' => [],
                        ]);
                    }
                }

                // PCS Group N → SUT N (LV AC cables)
                foreach ($suts->isNotEmpty() ? $pcsGroups : [] as $pcs) {
                    $sut = $suts->firstWhere('group_number', $pcs->group_number);
                    if (!$sut) {
                        // Map PCS to SUT when counts differ (round-robin)
                        $sutIndex = (($pcs->group_number - 1) % $suts->count()) + 1;
                        $sut = $suts->firstWhere('group_number', $sutIndex);
                    }
                    if ($sut) {
                        InterfaceRecord::create([
                            'project_id' => $project->id,
                            'from_node_id' => $pcs->id,
                            'to_node_id' => $sut->id,
                            'interface_type' => InterfaceType::Power,
                            'cable_type' => 'LV',
                            'generates_activities' => true,
                            'activity_types' => ['cable_install', 'cable_termination', 'point_to_point'],
                        ]);
                    }
                }

                // SUT → RMU or SUT → Switchroom (MV cables)
                // Routing depends on RMU topology
                $sutRmus = $nodes->filter(fn ($n) =>
                    $n->type === EquipmentNodeType::Rmu &&
                    $n->zone_number === $zone &&
                    $n->block_number === $block
                )->sortBy('group_number');

                $blockRmu = $nodes->first(fn ($n) =>
                    $n->type === EquipmentNodeType::BlockCollectorRmu &&
                    $n->zone_number === $zone &&
                    $n->block_number === $block
                );

                if ($sutRmus->isNotEmpty()) {
                    // SUT → SUT-level RMU (per_sut or per_sut_and_block)
                    foreach ($suts as $sut) {
                        $rmu = $sutRmus->firstWhere('group_number', $sut->group_number);
                        if ($rmu) {
                            InterfaceRecord::create([
                                'project_id' => $project->id,
                                'from_node_id' => $sut->id,
                                'to_node_id' => $rmu->id,
                                'interface_type' => InterfaceType::Power,
                                'cable_type' => 'MV',
                                'generates_activities' => false, // Activities handled by RMU generator
                                'activity_types' => [],
                            ]);
                        }
                    }

                    // SUT RMU → Block RMU or Switchroom
                    $rmuTarget = $blockRmu ?? $switchroom;
                    if ($rmuTarget) {
                        foreach ($sutRmus as $rmu) {
                            InterfaceRecord::create([
                                'project_id' => $project->id,
                                'from_node_id' => $rmu->id,
                                'to_node_id' => $rmuTarget->id,
                                'interface_type' => InterfaceType::Power,
                                'cable_type' => 'MV',
                                'generates_activities' => false,
                                'activity_types' => [],
                            ]);
                        }
                    }
                } elseif ($blockRmu) {
                    // SUT → Block collector RMU (per_block)
                    foreach ($suts as $sut) {
                        InterfaceRecord::create([
                            'project_id' => $project->id,
                            'from_node_id' => $sut->id,
                            'to_node_id' => $blockRmu->id,
                            'interface_type' => InterfaceType::Power,
                            'cable_type' => 'MV',
                            'generates_activities' => false,
                            'activity_types' => [],
                        ]);
                    }

                    // Block RMU → Switchroom
                    if ($switchroom) {
                        InterfaceRecord::create([
                            'project_id' => $project->id,
                            'from_node_id' => $blockRmu->id,
                            'to_node_id' => $switchroom->id,
                            'interface_type' => InterfaceType::Power,
                            'cable_type' => 'MV',
                            'generates_activities' => false,
                            'activity_types' => [],
                        ]);
                    }
                } elseif ($switchroom) {
                    // No RMU — direct SUT → Switchroom
                    foreach ($suts as $sut) {
                        InterfaceRecord::create([
                            'project_id' => $project->id,
                            'from_node_id' => $sut->id,
                            'to_node_id' => $switchroom->id,
                            'interface_type' => InterfaceType::Power,
                            'cable_type' => 'MV',
                            'generates_activities' => true,
                            'activity_types' => ['cable_install', 'cable_termination', 'point_to_point'],
                        ]);
                    }
                }
            }
        }

        // Switchroom → Transformer
        if ($switchroom && $transformer) {
            InterfaceRecord::create([
                'project_id' => $project->id,
                'from_node_id' => $switchroom->id,
                'to_node_id' => $transformer->id,
                'interface_type' => InterfaceType::Power,
                'cable_type' => 'MV',
                'generates_activities' => true,
                'activity_types' => ['cable_install', 'cable_termination'],
            ]);
        }

        // Transformer → Substation
        if ($transformer && $substation) {
            InterfaceRecord::create([
                'project_id' => $project->id,
                'from_node_id' => $transformer->id,
                'to_node_id' => $substation->id,
                'interface_type' => InterfaceType::Power,
                'cable_type' => 'MV',
                'generates_activities' => true,
                'activity_types' => ['cable_install', 'cable_termination'],
            ]);
        }
    }

    /**
     * Stage 8: Roll-up closures (CLAUDE.md §1, §5.5). Every milestone that
     * drives nothing downstream rolls up to a closure milestone for its phase
     * (top-level WBS branch), and each phase closure rolls up to a single
     * terminal Project Complete milestone. The end of the project is then the
     * only open finish in the schedule.
     *
     * Only milestones roll up: a task left without a successor is a genuine
     * logic gap and stays visible to OpenFinishRule.
     */
    private function generateRollupClosures(Project $project): void
    {
        $drivers = Relationship::where('project_id', $project->id)
            ->pluck('predecessor_id')
            ->flip();

        $dangling = ActivityInstance::where('project_id', $project->id)
            ->where('is_milestone', true)
            ->orderBy('id')
            ->get()
            ->reject(fn (ActivityInstance $a) => $drivers->has($a->id));

        if ($dangling->isEmpty()) {
            return;
        }

        $nodes = WbsNode::where('project_id', $project->id)->get()->keyBy('id');
        $branchOf = function (?int $nodeId) use ($nodes): ?WbsNode {
            $node = $nodeId !== null ? $nodes->get($nodeId) : null;
            while ($node !== null && $node->level > 1) {
                $node = $nodes->get($node->parent_id);
            }

            return $node?->level === 1 ? $node : null;
        };

        $milestonesBranch = $nodes->first(
            fn (WbsNode $n) => $n->level === 1 && $n->wbs_category === WbsCategory::Milestones
        );

        $projectComplete = ActivityInstance::create([
            'project_id' => $project->id,
            'package_instance_id' => null,
            'wbs_node_id' => $milestonesBranch?->id ?? $dangling->first()->wbs_node_id,
            'activity_code' => self::PROJECT_COMPLETE_CODE,
            'name' => 'Project Complete',
            'duration_days' => 0,
            'is_milestone' => true,
            'sequence' => 1,
            'p6_task_type' => P6TaskType::FinishMilestone,
        ]);

        $byBranch = $dangling->groupBy(fn (ActivityInstance $a) => $branchOf($a->wbs_node_id)?->id ?? 0);

        foreach ($byBranch as $branchId => $milestones) {
            $branch = $nodes->get($branchId);

            // Milestones that already sit in the Milestones branch (handover,
            // award dates…) and anything outside the WBS go straight to the end.
            if ($branch === null || $branch->wbs_category === WbsCategory::Milestones) {
                foreach ($milestones as $milestone) {
                    $this->rollup($project, $milestone, $projectComplete);
                }

                continue;
            }

            $closure = ActivityInstance::create([
                'project_id' => $project->id,
                'package_instance_id' => null,
                'wbs_node_id' => $branch->id,
                'activity_code' => 'PHASE-' . strtoupper($branch->wbs_category?->value ?? (string) $branch->id) . '-COMPLETE',
                'name' => "{$branch->name} Complete",
                'duration_days' => 0,
                'is_milestone' => true,
                'sequence' => 1,
                'p6_task_type' => P6TaskType::FinishMilestone,
            ]);

            foreach ($milestones as $milestone) {
                $this->rollup($project, $milestone, $closure);
            }

            $this->rollup($project, $closure, $projectComplete);
        }
    }

    private function rollup(Project $project, ActivityInstance $from, ActivityInstance $to): void
    {
        Relationship::create([
            'project_id' => $project->id,
            'predecessor_id' => $from->id,
            'successor_id' => $to->id,
            'relationship_type' => RelationshipType::FinishToStart,
            'lag_days' => 0,
            'origin' => RelationshipOrigin::Rollup,
        ]);
    }

    /**
     * Stage 9: Start-on-or-after constraints at the project start date for
     * the activities nothing drives that are meant to start the schedule:
     *  - root packages, whose template depends on nothing (NTP);
     *  - external receipt milestones — the planner replaces the date with
     *    the contractual receipt date.
     * Any other activity without a predecessor is a logic gap and is left
     * for OpenStartRule to report.
     */
    private function setProjectStartConstraint(Project $project): void
    {
        ActivityInstance::where('project_id', $project->id)
            ->whereDoesntHave('predecessorRelationships')
            ->where(fn ($q) => $q
                ->whereHas('packageInstance.template', fn ($t) => $t->whereDoesntHave('dependencyRules'))
                ->orWhere(fn ($external) => $external
                    ->where('is_milestone', true)
                    ->whereHas('packageInstance', fn ($p) => $p->where('ownership_mode', OwnershipMode::External))))
            ->update([
                'constraint_type' => ConstraintType::StartOnOrAfter,
                'constraint_date' => $project->start_date,
            ]);
    }

    // ── Architecture Config ─────────────────────────────────

    /**
     * Build the architecture configuration array that drives topology generation.
     * Determines which equipment types and cable activities are generated.
     */
    private function buildArchitectureConfig(Project $project): array
    {
        $arch = $project->bess_architecture;

        $config = match ($arch) {
            BessArchitecture::IntegratedContainer => [
                'generate_pcs_install' => false,
                'generate_dc_cables' => false,
                'generate_ac_cables' => true,
                'generate_sut' => true,
                'generate_mv_cables' => true,
                'pcs_per_sut' => null,
                'batteries_per_pcs' => null,
                'units_per_sut' => $project->containers_per_sut,
                'cross_block_sut' => false,
                'unit_label' => $project->container_unit_label ?? 'Battery Container',
            ],
            BessArchitecture::SeparatePcsOnePerSut => [
                'generate_pcs_install' => true,
                'generate_dc_cables' => true,
                'generate_ac_cables' => true,
                'generate_sut' => true,
                'generate_mv_cables' => true,
                'pcs_per_sut' => 1,
                'batteries_per_pcs' => $project->batteries_per_pcs,
                'cross_block_sut' => false,
                'unit_label' => 'Battery',
            ],
            BessArchitecture::SeparatePcsMultiPerSut => [
                'generate_pcs_install' => true,
                'generate_dc_cables' => true,
                'generate_ac_cables' => true,
                'generate_sut' => true,
                'generate_mv_cables' => true,
                'pcs_per_sut' => $project->pcs_per_sut,
                'batteries_per_pcs' => $project->batteries_per_pcs,
                'cross_block_sut' => false,
                'unit_label' => 'Battery',
            ],
            BessArchitecture::NoSut => [
                'generate_pcs_install' => true,
                'generate_dc_cables' => true,
                'generate_ac_cables' => false,
                'generate_sut' => false,
                'generate_mv_cables' => true,
                'pcs_per_sut' => null,
                'batteries_per_pcs' => $project->batteries_per_pcs,
                'cross_block_sut' => false,
                'unit_label' => 'Battery',
            ],
            BessArchitecture::Cluster => [
                'generate_pcs_install' => true,
                'generate_dc_cables' => true,
                'generate_ac_cables' => true,
                'generate_sut' => true,
                'generate_mv_cables' => true,
                'pcs_per_sut' => $project->pcs_per_sut,
                'batteries_per_pcs' => $project->batteries_per_pcs,
                'cross_block_sut' => true,
                'blocks_per_cluster_sut' => $project->blocks_per_cluster_sut,
                'unit_label' => 'Battery',
            ],
            default => [
                'generate_pcs_install' => true,
                'generate_dc_cables' => true,
                'generate_ac_cables' => true,
                'generate_sut' => true,
                'generate_mv_cables' => true,
                'cross_block_sut' => false,
                'unit_label' => 'Battery',
            ],
        };

        // RMU config
        $rmu = $project->rmu_topology;
        $config['generate_rmu'] = $rmu?->generatesRmu() ?? false;
        $config['rmu_topology'] = $rmu?->value ?? 'none';
        if ($config['generate_rmu'] && $project->rmu_on_mv_ring) {
            $config['ring_sequencing'] = true;
            $config['ring_voltage'] = $project->rmu_mv_ring_voltage;
        }

        return $config;
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * The activity that produces a resolved output: the external receipt
     * milestone, or the provider package's activity at the output's
     * produced_by_sequence. An output resolved through a containing package
     * (included_elsewhere) isn't defined on that package's template, so the
     * containing package's completion anchor stands in for it.
     */
    private function findProviderActivity(
        ProviderResolution $resolution,
        Collection $activitiesById,
        Collection $activitiesByPackage,
        Collection $packagesById,
    ): ?ActivityInstance {
        if ($resolution->provider_activity_instance_id) {
            return $activitiesById->get($resolution->provider_activity_instance_id);
        }

        $instance = $packagesById->get($resolution->provider_package_instance_id);
        if ($instance === null) {
            return null;
        }

        $activities = $activitiesByPackage->get($instance->id, collect());

        $outputDef = $instance->template->outputDefinitions
            ->firstWhere('output_key', $resolution->output_key);

        if ($outputDef === null) {
            return $this->completionAnchor($instance, $activities);
        }

        return $activities->firstWhere('sequence', $outputDef->produced_by_sequence);
    }

    /**
     * A package's completion anchor: the activity referenced by its output
     * definition's `produced_by_sequence` (the package's true sign-off node),
     * else its first milestone. Some packages — notably procurement — have
     * multiple milestones (e.g. PO Award and Delivered); anchoring on the
     * wrong one creates a cycle since the later milestone is already
     * downstream of the earlier one.
     */
    private function completionAnchor(PackageInstance $instance, Collection $activities): ?ActivityInstance
    {
        $producedBySeq = $instance->template->outputDefinitions
            ->pluck('produced_by_sequence')
            ->filter()
            ->first();

        return $producedBySeq !== null
            ? $activities->firstWhere('sequence', $producedBySeq)
            : $activities->firstWhere('is_milestone', true);
    }

    private function buildActivityCodePrefix(PackageInstance $instance): string
    {
        $code = strtoupper(str_replace('_', '-', $instance->template->code));

        if ($instance->topology_label) {
            $code .= '-Z' . $instance->zone_number . 'B' . $instance->block_number . 'G' . $instance->group_number;
        }

        return $code;
    }

    private function buildActivityName(PackageInstance $instance, string $templateName): string
    {
        if ($instance->topology_label) {
            return $templateName . ' - ' . $instance->topology_label;
        }

        return $templateName;
    }

    private function clearCompilation(Project $project): void
    {
        Relationship::where('project_id', $project->id)->delete();
        ActivityInstance::where('project_id', $project->id)->forceDelete();
        EquipmentNode::where('project_id', $project->id)->delete();
        InterfaceRecord::where('project_id', $project->id)->delete();
    }
}