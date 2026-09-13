<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\BlockSequencingMode;
use App\Enums\EquipmentNodeType;
use App\Enums\P6TaskType;
use App\Enums\RelationshipOrigin;
use App\Enums\RelationshipType;
use App\Enums\WbsCategory;
use App\Models\ActivityInstance;
use App\Models\BlockConfiguration;
use App\Models\EquipmentNode;
use App\Models\Project;
use App\Models\Relationship;
use App\Models\WbsNode;

/**
 * Generates all Battery, PCS, and SUT installation activities programmatically
 * from the topology configuration. Respects the BESS architecture selection
 * to conditionally suppress PCS, DC cable, AC cable, or SUT activities.
 *
 * For each block:
 *   Civil Works     → group-first WBS, 6 activities per group
 *   Mechanical      → flat list, 1 activity per group
 *   Electrical      → flat list, 1 activity per group (if applicable)
 *   Termination     → flat list, 1 activity per group (if applicable)
 *   Milestones      → group complete, block complete
 *
 * Cross-equipment dual predecessors are applied after all activities are created.
 */
class TopologyActivityGenerator
{
    // ── Civil activity definitions (same for Battery, PCS, SUT) ──
    private const CIVIL_ACTIVITIES = [
        ['suffix' => 'EXC', 'name' => 'Excavation and Blinding', 'duration' => 1],
        ['suffix' => 'REB', 'name' => 'Rebar, Anchor Bolts and Embedments', 'duration' => 2],
        ['suffix' => 'FWK', 'name' => 'Formwork', 'duration' => 1],
        ['suffix' => 'POU', 'name' => 'Concrete Pour', 'duration' => 1],
        ['suffix' => 'CUR', 'name' => 'Concrete Curing', 'duration' => 7],
        ['suffix' => 'FIN', 'name' => 'Formwork Strip and Pad Finish', 'duration' => 1],
    ];

    // ── Duration defaults per equipment type ──
    private const DURATIONS = [
        'battery' => ['mechanical' => 2, 'electrical' => 2, 'termination' => 2],
        'pcs' => ['mechanical' => 2, 'electrical' => 2, 'termination' => 2],
        'sut' => ['mechanical' => 3, 'electrical' => 3, 'termination' => 2],
    ];

    // ── RMU activity definitions ──
    private const RMU_CIVIL_ACTIVITIES = [
        ['suffix' => 'EXC', 'name' => 'Excavation and Blinding', 'duration' => 1],
        ['suffix' => 'POU', 'name' => 'Concrete Pad Pour', 'duration' => 1],
        ['suffix' => 'CUR', 'name' => 'Concrete Curing', 'duration' => 3],
        ['suffix' => 'FIN', 'name' => 'Pad Finish and Bolts', 'duration' => 1],
    ];

    private const RMU_DURATIONS = [
        'mechanical' => 2,      // RMU unit installation and fixing
        'mv_cable_in' => 2,     // MV cable from SUT to RMU
        'mv_cable_out' => 2,    // MV cable from RMU to switchroom / next RMU
        'termination' => 1,     // Cable termination
        'control' => 2,         // Control and protection cabling
        'testing' => 2,         // Functional testing
    ];

    /** @var array<string, ActivityInstance> Keyed by "{type}_{z}_{b}_{g}_{phase}" for cross-referencing */
    private array $activityMap = [];

    /** @var array Architecture configuration from GraphCompilerService */
    private array $archConfig = [];

    public function generate(Project $project, array $archConfig = []): void
    {
        $this->activityMap = [];
        $this->archConfig = $archConfig;

        $blocks = BlockConfiguration::where('project_id', $project->id)
            ->with('zoneConfiguration')
            ->orderBy('zone_number')
            ->orderBy('block_number')
            ->get();

        $nodes = EquipmentNode::where('project_id', $project->id)->get();

        $generatePcs = $this->archConfig['generate_pcs_install'] ?? true;
        $generateDc = $this->archConfig['generate_dc_cables'] ?? true;
        $generateSut = $this->archConfig['generate_sut'] ?? true;
        $unitLabel = $this->archConfig['unit_label'] ?? 'Battery';

        // Per-zone state — reset when zone changes so zones remain strictly parallel
        $sequencingMode = $project->block_sequencing_mode ?? BlockSequencingMode::FinishToStart;
        $sequencingLag = (int) ($project->block_sequencing_lag_days ?? 0);
        $previousBlockCivilComplete = null;
        $previousBlockWorkfront = null;
        $currentZone = null;

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;
            $zoneLabel = $block->zoneConfiguration->displayLabel();
            $blockLabel = $block->displayLabel();

            // Zones are strictly parallel — reset chain state on zone boundary
            if ($currentZone !== $z) {
                $currentZone = $z;
                $previousBlockCivilComplete = null;
                $previousBlockWorkfront = null;
            }

            // Get the Construction branch
            $constructionBranch = WbsNode::where('project_id', $project->id)
                ->where('level', 1)
                ->where('wbs_category', WbsCategory::Construction)
                ->firstOrFail();

            // Create Block-level WBS: Construction > Zone > Block
            $zoneWbs = $this->ensureWbs($project, $constructionBranch, $zoneLabel, 2);
            $blockWbs = $this->ensureWbs($project, $zoneWbs, $blockLabel, 3);

            // Block Workfront Released milestone — chained per project sequencing mode
            $workfrontMs = $this->createMilestone($project, $blockWbs,
                "Z{$z}-B{$b}-WFR",
                "{$blockLabel} Workfront Released",
            );
            $this->linkBlockToPrevious(
                $project,
                $workfrontMs,
                $previousBlockCivilComplete,
                $previousBlockWorkfront,
                $sequencingMode,
                $sequencingLag,
            );
            $this->activityMap["workfront_{$z}_{$b}"] = $workfrontMs;
            $previousBlockWorkfront = $workfrontMs;

            // Create discipline WBS under Block
            $civilWbs = $this->ensureWbs($project, $blockWbs, 'Civil Works', 4);
            $mechWbs = $this->ensureWbs($project, $blockWbs, 'Mechanical', 4);
            $elecWbs = $this->ensureWbs($project, $blockWbs, 'Electrical Cable Installation', 4);
            $termWbs = $this->ensureWbs($project, $blockWbs, 'Termination', 4);

            // Track all civil last activities for this block to create Block Civil Complete
            $blockCivilLastActivities = [];

            // ── Battery / Container Groups ───────────────────
            $batteryNodes = $nodes->filter(fn ($n) =>
                $n->type === EquipmentNodeType::BatteryGroup
                && $n->zone_number === $z && $n->block_number === $b
            )->sortBy('group_number');

            $batCivilWbs = $this->ensureWbs($project, $civilWbs, "{$unitLabel} Foundation", 5);
            $batMechWbs = $this->ensureWbs($project, $mechWbs, "{$unitLabel} Installation", 5);
            $batElecWbs = $generateDc
                ? $this->ensureWbs($project, $elecWbs, "DC Cable Installation {$unitLabel} to PCS", 5)
                : $elecWbs;
            $batTermWbs = $generateDc
                ? $this->ensureWbs($project, $termWbs, "Terminate DC Cable at {$unitLabel} and PCS", 5)
                : $termWbs;

            foreach ($batteryNodes as $node) {
                $g = $node->group_number;
                $unitFrom = $node->unit_from;
                $unitTo = $node->unit_to;
                $rangeLabel = $unitFrom === $unitTo ? "{$unitLabel} {$unitFrom}" : "{$unitLabel}s {$unitFrom}-{$unitTo}";
                $groupTag = "Group {$g}: {$rangeLabel}";
                $isPartial = $node->is_partial;
                $partialRatio = $isPartial && $node->actual_unit_count
                    ? $node->actual_unit_count / ($blocks->firstWhere('zone_number', $z)?->battery_group_size ?? 4)
                    : 1.0;

                $codePrefix = "Z{$z}-B{$b}-BAT-G{$g}";

                // Civil: group-first WBS with 6 activities
                $civilGroupWbs = $this->ensureWbs($project, $batCivilWbs, "{$unitLabel} {$groupTag}", 6);
                $civilLast = $this->generateCivilActivities(
                    $project, $civilGroupWbs, $codePrefix, "{$unitLabel} Pad", $groupTag, $partialRatio, $workfrontMs
                );
                $blockCivilLastActivities[] = $civilLast;

                // Mechanical: 1 activity, flat
                $mechAct = $this->createActivity($project, $batMechWbs,
                    "{$codePrefix}-MEC",
                    "{$unitLabel} Container Installation -{$groupTag}",
                    $this->scaleDuration(self::DURATIONS['battery']['mechanical'], $partialRatio),
                );
                $this->link($project, $civilLast, $mechAct);
                $this->activityMap["bat_{$z}_{$b}_{$g}_mech"] = $mechAct;

                // The last activity in the battery chain — depends on architecture
                $lastBatActivity = $mechAct;

                // DC Cable: only if architecture requires separate DC cables
                if ($generateDc) {
                    $elecAct = $this->createActivity($project, $batElecWbs,
                        "{$codePrefix}-CAB",
                        "DC Cable Installation from {$unitLabel} to PCS -{$groupTag}",
                        $this->scaleDuration(self::DURATIONS['battery']['electrical'], $partialRatio),
                    );
                    $this->link($project, $mechAct, $elecAct);
                    $this->activityMap["bat_{$z}_{$b}_{$g}_elec"] = $elecAct;

                    $termAct = $this->createActivity($project, $batTermWbs,
                        "{$codePrefix}-TER",
                        "Terminate Cable at {$unitLabel} and PCS -{$groupTag}",
                        $this->scaleDuration(self::DURATIONS['battery']['termination'], $partialRatio),
                    );
                    $this->link($project, $elecAct, $termAct);

                    $lastBatActivity = $termAct;
                }

                $this->activityMap["bat_{$z}_{$b}_{$g}_complete"] = $lastBatActivity;
            }

            // ── PCS Groups — only if architecture has separate PCS ──
            $pcsNodes = $nodes->filter(fn ($n) =>
                $n->type === EquipmentNodeType::PcsGroup
                && $n->zone_number === $z && $n->block_number === $b
            )->sortBy('group_number');

            if ($generatePcs && $pcsNodes->isNotEmpty()) {
                $pcsCivilWbs = $this->ensureWbs($project, $civilWbs, 'PCS Foundation', 5);
                $pcsMechWbs = $this->ensureWbs($project, $mechWbs, 'PCS Installation', 5);

                $generateAc = $this->archConfig['generate_ac_cables'] ?? true;
                $pcsElecWbs = $generateAc
                    ? $this->ensureWbs($project, $elecWbs, 'AC Cable Installation PCS to SUT', 5)
                    : (!$generateSut
                        ? $this->ensureWbs($project, $elecWbs, 'MV Cable Installation PCS to Switchboard', 5)
                        : $elecWbs);
                $pcsTermWbs = $generateAc
                    ? $this->ensureWbs($project, $termWbs, 'Terminate AC Cable at PCS and SUT', 5)
                    : (!$generateSut
                        ? $this->ensureWbs($project, $termWbs, 'Terminate MV Cable at PCS and Switchboard', 5)
                        : $termWbs);

                foreach ($pcsNodes as $node) {
                    $g = $node->group_number;
                    $unitFrom = $node->unit_from;
                    $unitTo = $node->unit_to;
                    $rangeLabel = $unitFrom === $unitTo ? "PCS {$unitFrom}" : "PCS {$unitFrom}-{$unitTo}";
                    $groupTag = "Group {$g}: {$rangeLabel}";
                    $isPartial = $node->is_partial;
                    $partialRatio = $isPartial && $node->actual_unit_count
                        ? $node->actual_unit_count / ($blocks->firstWhere('zone_number', $z)?->pcs_group_size ?? 2)
                        : 1.0;

                    $codePrefix = "Z{$z}-B{$b}-PCS-G{$g}";

                    // Civil
                    $civilGroupWbs = $this->ensureWbs($project, $pcsCivilWbs, "PCS {$groupTag}", 6);
                    $civilLast = $this->generateCivilActivities(
                        $project, $civilGroupWbs, $codePrefix, 'PCS Pad', $groupTag, $partialRatio, $workfrontMs
                    );
                    $blockCivilLastActivities[] = $civilLast;

                    // Mechanical
                    $mechAct = $this->createActivity($project, $pcsMechWbs,
                        "{$codePrefix}-MEC",
                        "PCS Unit Installation -{$groupTag}",
                        $this->scaleDuration(self::DURATIONS['pcs']['mechanical'], $partialRatio),
                    );
                    $this->link($project, $civilLast, $mechAct);
                    $this->activityMap["pcs_{$z}_{$b}_{$g}_mech"] = $mechAct;

                    $lastPcsActivity = $mechAct;

                    // AC Cable: PCS to SUT (only if architecture has AC cables)
                    if ($generateAc) {
                        $elecAct = $this->createActivity($project, $pcsElecWbs,
                            "{$codePrefix}-CAB",
                            "AC Cable Installation from PCS to SUT -{$groupTag}",
                            $this->scaleDuration(self::DURATIONS['pcs']['electrical'], $partialRatio),
                        );
                        $this->link($project, $mechAct, $elecAct);
                        $this->activityMap["pcs_{$z}_{$b}_{$g}_elec"] = $elecAct;

                        $termAct = $this->createActivity($project, $pcsTermWbs,
                            "{$codePrefix}-TER",
                            "Terminate Cable at PCS and SUT -{$groupTag}",
                            $this->scaleDuration(self::DURATIONS['pcs']['termination'], $partialRatio),
                        );
                        $this->link($project, $elecAct, $termAct);

                        $lastPcsActivity = $termAct;
                    } elseif (!$generateSut) {
                        // No SUT architecture: MV cable from PCS to switchboard
                        $elecAct = $this->createActivity($project, $pcsElecWbs,
                            "{$codePrefix}-CAB",
                            "MV Cable Installation from PCS to MV Switchboard -{$groupTag}",
                            $this->scaleDuration(self::DURATIONS['pcs']['electrical'], $partialRatio),
                        );
                        $this->link($project, $mechAct, $elecAct);
                        $this->activityMap["pcs_{$z}_{$b}_{$g}_elec"] = $elecAct;

                        $termAct = $this->createActivity($project, $pcsTermWbs,
                            "{$codePrefix}-TER",
                            "Terminate MV Cable at PCS and Switchboard -{$groupTag}",
                            $this->scaleDuration(self::DURATIONS['pcs']['termination'], $partialRatio),
                        );
                        $this->link($project, $elecAct, $termAct);

                        $lastPcsActivity = $termAct;
                    }

                    $this->activityMap["pcs_{$z}_{$b}_{$g}_complete"] = $lastPcsActivity;
                }
            }

            // ── SUTs — only if architecture has SUTs ─────────
            $sutNodes = collect();
            if ($generateSut) {
                $sutNodes = $nodes->filter(fn ($n) =>
                    $n->type === EquipmentNodeType::Sut
                    && $n->zone_number === $z && $n->block_number === $b
                )->sortBy('group_number');

                if ($sutNodes->isNotEmpty()) {
                    $sutCivilWbs = $this->ensureWbs($project, $civilWbs, 'SUT Foundation', 5);
                    $sutMechWbs = $this->ensureWbs($project, $mechWbs, 'SUT Installation', 5);
                    $sutElecWbs = $this->ensureWbs($project, $elecWbs, 'MV Cable Installation SUT to Switchroom', 5);
                    $sutTermWbs = $this->ensureWbs($project, $termWbs, 'Terminate MV Cable at SUT and Switchroom', 5);

                    foreach ($sutNodes as $node) {
                        $s = $node->group_number;
                        $sutTag = "SUT {$s}";
                        $codePrefix = "Z{$z}-B{$b}-SUT-" . str_pad((string) $s, 2, '0', STR_PAD_LEFT);

                        // Civil
                        $civilSutWbs = $this->ensureWbs($project, $sutCivilWbs, $sutTag, 6);
                        $civilLast = $this->generateCivilActivities(
                            $project, $civilSutWbs, $codePrefix, 'SUT Pad', $sutTag, 1.0, $workfrontMs
                        );
                        $blockCivilLastActivities[] = $civilLast;

                        // Mechanical
                        $mechAct = $this->createActivity($project, $sutMechWbs,
                            "{$codePrefix}-MEC",
                            "SUT Installation -{$sutTag}",
                            self::DURATIONS['sut']['mechanical'],
                        );
                        $this->link($project, $civilLast, $mechAct);
                        $this->activityMap["sut_{$z}_{$b}_{$s}_mech"] = $mechAct;

                        // Electrical
                        $elecAct = $this->createActivity($project, $sutElecWbs,
                            "{$codePrefix}-CAB",
                            "MV Cable Installation from SUT to Switchroom -{$sutTag}",
                            self::DURATIONS['sut']['electrical'],
                        );
                        $this->link($project, $mechAct, $elecAct);
                        $this->activityMap["sut_{$z}_{$b}_{$s}_elec"] = $elecAct;

                        // Termination
                        $termAct = $this->createActivity($project, $sutTermWbs,
                            "{$codePrefix}-TER",
                            "Terminate MV Cable at SUT and Switchroom -{$sutTag}",
                            self::DURATIONS['sut']['termination'],
                        );
                        $this->link($project, $elecAct, $termAct);

                        $this->activityMap["sut_{$z}_{$b}_{$s}_complete"] = $termAct;
                    }
                }
            }

            // ── Block Civil Complete milestone ───────────────────
            // All civil works in this block must finish before next block's workfront releases
            if (!empty($blockCivilLastActivities)) {
                $blockCivilMs = $this->createMilestone($project, $blockWbs,
                    "Z{$z}-B{$b}-CIV-COM",
                    "{$blockLabel} All Civil Works Complete",
                );
                foreach ($blockCivilLastActivities as $civilAct) {
                    $this->link($project, $civilAct, $blockCivilMs);
                }
                $this->activityMap["civil_block_{$z}_{$b}_complete"] = $blockCivilMs;
                $previousBlockCivilComplete = $blockCivilMs;
            }

            // ── Block completion milestones ───────────────────
            $this->generateBlockMilestones(
                $project, $blockWbs, $z, $b,
                $batteryNodes,
                $generatePcs ? $pcsNodes : collect(),
                $generateSut ? $sutNodes : collect(),
            );
        }

        // ── RMU activities ───────────────────────────────────────
        $generateRmu = $this->archConfig['generate_rmu'] ?? false;
        if ($generateRmu) {
            $this->generateRmuActivities($project, $blocks, $nodes);
        }

        // ── Design-to-topology link ─────────────────────────────
        $this->linkBessFdnDesignToWorkfront($project, $blocks);
        $this->linkFeederIfcToMvCableInstall($project, $blocks, $nodes);

        // ── Block-level BESS Pre-Commissioning ─────────────────
        $this->generatePerBlockBessPreCommActivities($project, $blocks);

        // ── Block-level BESS Live Energisation ─────────────────
        $this->generatePerBlockBessLiveEnergisationActivities($project, $blocks);

        // ── Project-level BESS Online Commissioning ────────────
        $this->generateBessOnlineCommissioningActivities($project);

        // ── Cross-equipment dual predecessors ─────────────────
        $this->applyCrossEquipmentPredecessors($project, $blocks, $nodes);
    }

    // ── Civil activity generation ────────────────────────────

    private function generateCivilActivities(
        Project $project,
        WbsNode $wbsNode,
        string $codePrefix,
        string $padPrefix,
        string $groupTag,
        float $partialRatio,
        ?ActivityInstance $predecessor = null,
    ): ActivityInstance {
        $previous = $predecessor;

        foreach (self::CIVIL_ACTIVITIES as $i => $def) {
            $seq = $i + 1;
            $duration = $this->scaleDuration($def['duration'], $partialRatio);
            // Curing minimum 3 days even for partial
            if ($def['suffix'] === 'CUR') {
                $duration = max(3, $duration);
            }

            $activity = $this->createActivity(
                $project,
                $wbsNode,
                "{$codePrefix}-{$def['suffix']}",
                "{$padPrefix} {$def['name']} -{$groupTag}",
                $duration,
                $seq,
            );

            if ($previous) {
                $this->link($project, $previous, $activity);
            }

            $previous = $activity;
        }

        return $previous; // Last civil activity (Formwork Strip and Pad Finish)
    }

    // ── Block milestones ─────────────────────────────────────

    private function generateBlockMilestones(
        Project $project,
        WbsNode $blockWbs,
        int $z,
        int $b,
        $batteryNodes,
        $pcsNodes,
        $sutNodes,
    ): void {
        // Block Battery Groups Complete
        if ($batteryNodes->isNotEmpty()) {
            $batBlockMs = $this->createMilestone($project, $blockWbs,
                "Z{$z}-B{$b}-BAT-BLK",
                "Block {$b} -All Battery Groups Installation Complete",
            );
            foreach ($batteryNodes as $node) {
                $groupMs = $this->activityMap["bat_{$z}_{$b}_{$node->group_number}_complete"] ?? null;
                if ($groupMs) {
                    $this->link($project, $groupMs, $batBlockMs);
                }
            }
            $this->activityMap["bat_block_{$z}_{$b}_complete"] = $batBlockMs;
        }

        // Block PCS Groups Complete
        if ($pcsNodes->isNotEmpty()) {
            $pcsBlockMs = $this->createMilestone($project, $blockWbs,
                "Z{$z}-B{$b}-PCS-BLK",
                "Block {$b} -All PCS Groups Installation Complete",
            );
            foreach ($pcsNodes as $node) {
                $groupMs = $this->activityMap["pcs_{$z}_{$b}_{$node->group_number}_complete"] ?? null;
                if ($groupMs) {
                    $this->link($project, $groupMs, $pcsBlockMs);
                }
            }
            $this->activityMap["pcs_block_{$z}_{$b}_complete"] = $pcsBlockMs;
        }

        // Block SUTs Complete
        if ($sutNodes->isNotEmpty()) {
            $sutBlockMs = $this->createMilestone($project, $blockWbs,
                "Z{$z}-B{$b}-SUT-BLK",
                "Block {$b} -All SUTs Installation Complete",
            );
            foreach ($sutNodes as $node) {
                $sutMs = $this->activityMap["sut_{$z}_{$b}_{$node->group_number}_complete"] ?? null;
                if ($sutMs) {
                    $this->link($project, $sutMs, $sutBlockMs);
                }
            }
            $this->activityMap["sut_block_{$z}_{$b}_complete"] = $sutBlockMs;
        }

        // Block Complete (all sub-milestones that exist)
        $blockMs = $this->createMilestone($project, $blockWbs,
            "Z{$z}-B{$b}-COMPLETE",
            "Block {$b} Complete",
        );

        foreach (['bat_block', 'pcs_block', 'sut_block'] as $prefix) {
            $subMs = $this->activityMap["{$prefix}_{$z}_{$b}_complete"] ?? null;
            if ($subMs) {
                $this->link($project, $subMs, $blockMs);
            }
        }

        $this->activityMap["block_{$z}_{$b}_complete"] = $blockMs;
    }

    // ── Design-to-topology link ────────────────────────────────

    /**
     * Link the BESS Foundation Design IFC Approved milestone to the first
     * block's Workfront Released milestone so that BESS civil work cannot
     * start until the foundation design is issued for construction.
     */
    private function linkBessFdnDesignToWorkfront(Project $project, $blocks): void
    {
        $bessFdnApproved = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'civil_bess_fdn_ifc'))
            ->where('is_milestone', true)
            ->first();

        if ($bessFdnApproved === null) {
            return;
        }

        $firstBlock = $blocks->first();
        if ($firstBlock === null) {
            return;
        }

        $workfront = $this->activityMap["workfront_{$firstBlock->zone_number}_{$firstBlock->block_number}"] ?? null;
        if ($workfront) {
            $this->linkIfNotExists($project, $bessFdnApproved, $workfront);
        }
    }

    /**
     * Link the Collector/Feeder Design IFC milestone to every MV cable
     * installation activity (the construction work it gates by definition).
     *
     * Covers the three architecture variants:
     *   - SUT to Switchroom MV cables (`*-SUT-*-CAB` and `*-SUT-*-TER`)
     *   - PCS to Switchboard MV cables (no-SUT architectures)
     *   - RMU MV cables in/out and their terminations
     */
    private function linkFeederIfcToMvCableInstall(Project $project, $blocks, $nodes): void
    {
        $feederIfc = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'elec_p_feeder_ifc'))
            ->where('is_milestone', true)
            ->first();

        if ($feederIfc === null) {
            return;
        }

        $generatePcs = $this->archConfig['generate_pcs_install'] ?? true;
        $generateSut = $this->archConfig['generate_sut'] ?? true;
        $generateAc = $this->archConfig['generate_ac_cables'] ?? true;
        $generateRmu = $this->archConfig['generate_rmu'] ?? false;

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;

            // SUT → Switchroom MV cables
            if ($generateSut) {
                $sutNodes = $nodes->filter(fn ($n) =>
                    $n->type === EquipmentNodeType::Sut
                    && $n->zone_number === $z && $n->block_number === $b
                );
                foreach ($sutNodes as $sutNode) {
                    $s = $sutNode->group_number;
                    $sutElec = $this->activityMap["sut_{$z}_{$b}_{$s}_elec"] ?? null;
                    if ($sutElec) {
                        $this->linkIfNotExists($project, $feederIfc, $sutElec);
                    }
                }
            }

            // PCS → Switchboard MV cables (only when no-AC and no-SUT architecture
            // routes MV directly off PCS — the PCS-CAB activity then carries MV)
            if ($generatePcs && ! $generateAc && ! $generateSut) {
                $pcsNodes = $nodes->filter(fn ($n) =>
                    $n->type === EquipmentNodeType::PcsGroup
                    && $n->zone_number === $z && $n->block_number === $b
                );
                foreach ($pcsNodes as $pcsNode) {
                    $g = $pcsNode->group_number;
                    $pcsElec = $this->activityMap["pcs_{$z}_{$b}_{$g}_elec"] ?? null;
                    if ($pcsElec) {
                        $this->linkIfNotExists($project, $feederIfc, $pcsElec);
                    }
                }
            }
        }

        // RMU MV cables (in/out) — same gate; topology-agnostic across the
        // per-SUT, per-block, per-zone, and two-level RMU variants.
        if ($generateRmu) {
            $rmuMvActs = ActivityInstance::where('project_id', $project->id)
                ->where(function ($q) {
                    $q->where('activity_code', 'like', '%-RMU-%-MVI')
                      ->orWhere('activity_code', 'like', '%-RMU-%-MVO')
                      ->orWhere('activity_code', 'like', '%-BRMU-MVO')
                      ->orWhere('activity_code', 'like', '%-BRMU-MVI-%')
                      ->orWhere('activity_code', 'like', '%-ZRMU-MVO');
                })
                ->get();
            foreach ($rmuMvActs as $act) {
                $this->linkIfNotExists($project, $feederIfc, $act);
            }
        }
    }

    // ── Cross-equipment dual predecessors ─────────────────────

    private function applyCrossEquipmentPredecessors(Project $project, $blocks, $nodes): void
    {
        $generatePcs = $this->archConfig['generate_pcs_install'] ?? true;
        $generateSut = $this->archConfig['generate_sut'] ?? true;

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;

            $batteryNodes = $nodes->filter(fn ($n) =>
                $n->type === EquipmentNodeType::BatteryGroup
                && $n->zone_number === $z && $n->block_number === $b
            );

            // Battery DC Cable depends on BOTH battery mech AND PCS mech
            // Only applies when PCS exists as separate equipment
            if ($generatePcs) {
                foreach ($batteryNodes as $batNode) {
                    $g = $batNode->group_number;
                    $batElec = $this->activityMap["bat_{$z}_{$b}_{$g}_elec"] ?? null;
                    $pcsMech = $this->activityMap["pcs_{$z}_{$b}_{$g}_mech"] ?? null;

                    if ($batElec && $pcsMech) {
                        $this->link($project, $pcsMech, $batElec);
                    }
                }

                // PCS AC Cable depends on BOTH PCS mech AND SUT mech
                if ($generateSut) {
                    $pcsNodes = $nodes->filter(fn ($n) =>
                        $n->type === EquipmentNodeType::PcsGroup
                        && $n->zone_number === $z && $n->block_number === $b
                    );

                    $sutNodes = $nodes->filter(fn ($n) =>
                        $n->type === EquipmentNodeType::Sut
                        && $n->zone_number === $z && $n->block_number === $b
                    );

                    $sutList = $sutNodes->sortBy('group_number')->values();
                    foreach ($pcsNodes as $pcsNode) {
                        $g = $pcsNode->group_number;
                        $pcsElec = $this->activityMap["pcs_{$z}_{$b}_{$g}_elec"] ?? null;

                        if ($sutList->isNotEmpty() && $pcsElec) {
                            $sutIndex = (($g - 1) % $sutList->count());
                            $sutNum = $sutList[$sutIndex]->group_number;
                            $sutMech = $this->activityMap["sut_{$z}_{$b}_{$sutNum}_mech"] ?? null;
                            if ($sutMech) {
                                $this->link($project, $sutMech, $pcsElec);
                            }
                        }
                    }
                }
            }

            // SUT MV Cable depends on switchroom building complete
            if ($generateSut) {
                $srComplete = ActivityInstance::where('project_id', $project->id)
                    ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'con_sr_civil'))
                    ->where('is_milestone', true)
                    ->first();

                if ($srComplete) {
                    $sutNodes = $nodes->filter(fn ($n) =>
                        $n->type === EquipmentNodeType::Sut
                        && $n->zone_number === $z && $n->block_number === $b
                    );

                    foreach ($sutNodes as $sutNode) {
                        $sutElec = $this->activityMap["sut_{$z}_{$b}_{$sutNode->group_number}_elec"] ?? null;
                        if ($sutElec) {
                            $this->linkIfNotExists($project, $srComplete, $sutElec);
                        }
                    }
                }
            }
        }
    }

    // ── RMU Activity Generation ─────────────────────────────

    private function generateRmuActivities(Project $project, $blocks, $nodes): void
    {
        $rmuTopology = $this->archConfig['rmu_topology'] ?? 'none';
        $ringSequencing = $this->archConfig['ring_sequencing'] ?? false;

        match ($rmuTopology) {
            'per_sut' => $this->generatePerSutRmuActivities($project, $blocks, $nodes, $ringSequencing),
            'per_block' => $this->generatePerBlockRmuActivities($project, $blocks, $nodes, $ringSequencing),
            'per_zone' => $this->generatePerZoneRmuActivities($project, $blocks, $nodes, $ringSequencing),
            'per_sut_and_block' => $this->generateTwoLevelRmuActivities($project, $blocks, $nodes, $ringSequencing),
            default => null,
        };
    }

    /**
     * Per-SUT RMU: one RMU at each SUT output.
     * Activities are distributed across discipline WBS nodes:
     *   Civil Works > RMU Foundation > RMU N
     *   Mechanical (flat)
     *   Electrical Cable Installation (flat)
     *   Termination (flat)
     */
    private function generatePerSutRmuActivities(Project $project, $blocks, $nodes, bool $ringSequencing): void
    {
        $rmuNodes = $nodes->filter(fn ($n) => $n->type === EquipmentNodeType::Rmu)
            ->sortBy(['zone_number', 'block_number', 'group_number']);

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;

            $blockRmus = $rmuNodes->filter(fn ($n) => $n->zone_number === $z && $n->block_number === $b)
                ->sortBy('group_number');

            if ($blockRmus->isEmpty()) {
                continue;
            }

            // Re-use the existing block discipline WBS nodes
            $wbs = $this->getBlockDisciplineWbs($project, $block);
            $rmuCivilWbs = $this->ensureWbs($project, $wbs['civil'], 'RMU Foundation', 5);
            $rmuMechWbs = $this->ensureWbs($project, $wbs['mech'], 'RMU Installation', 5);
            $rmuTestWbs = $this->ensureWbs($project, $wbs['mech'], 'RMU Testing', 5);
            $mvSutToRmuWbs = $this->ensureWbs($project, $wbs['elec'], 'MV Cable Installation SUT to RMU', 5);
            $mvRmuToSrWbs = $this->ensureWbs($project, $wbs['elec'], 'MV Cable Installation RMU to Switchroom', 5);
            $rmuCtrlWbs = $this->ensureWbs($project, $wbs['elec'], 'RMU Control and Protection Cabling', 5);
            $termSutToRmuWbs = $this->ensureWbs($project, $wbs['term'], 'Terminate MV Cable at SUT and RMU', 5);
            $termRmuToSrWbs = $this->ensureWbs($project, $wbs['term'], 'Terminate MV Cable at RMU and Switchroom', 5);

            $prevTermAct = null;
            $blockRmuTestActs = [];

            foreach ($blockRmus as $rmuNode) {
                $s = $rmuNode->group_number;
                $rmuTag = "RMU {$s}";
                $codePrefix = "Z{$z}-B{$b}-RMU-" . str_pad((string) $s, 2, '0', STR_PAD_LEFT);

                // Civil: under Civil Works > RMU Foundation > RMU N
                $rmuCivilUnitWbs = $this->ensureWbs($project, $rmuCivilWbs, $rmuTag, 6);
                $sutComplete = $this->activityMap["sut_{$z}_{$b}_{$s}_complete"] ?? null;
                $civilLast = $this->generateRmuCivilActivities($project, $rmuCivilUnitWbs, $codePrefix, $rmuTag, $sutComplete);

                // Mechanical: under Mechanical > RMU Installation
                $mechAct = $this->createActivity($project, $rmuMechWbs,
                    "{$codePrefix}-MEC", "RMU Unit Installation -{$rmuTag}",
                    self::RMU_DURATIONS['mechanical'],
                );
                $this->link($project, $civilLast, $mechAct);

                // Electrical: MV cable in (SUT→RMU)
                $mvInAct = $this->createActivity($project, $mvSutToRmuWbs,
                    "{$codePrefix}-MVI", "MV Cable Installation SUT to RMU -{$rmuTag}",
                    self::RMU_DURATIONS['mv_cable_in'],
                );
                $this->link($project, $mechAct, $mvInAct);

                // Termination: MV cable in term
                $mvInTerm = $this->createActivity($project, $termSutToRmuWbs,
                    "{$codePrefix}-MIT", "Terminate MV Cable at SUT and RMU -{$rmuTag}",
                    self::RMU_DURATIONS['termination'],
                );
                $this->link($project, $mvInAct, $mvInTerm);

                // Electrical: MV cable out (RMU→Switchroom)
                $mvOutAct = $this->createActivity($project, $mvRmuToSrWbs,
                    "{$codePrefix}-MVO", "MV Cable Installation RMU to Switchroom -{$rmuTag}",
                    self::RMU_DURATIONS['mv_cable_out'],
                );
                $this->link($project, $mvInTerm, $mvOutAct);

                if ($ringSequencing && $prevTermAct !== null) {
                    $this->link($project, $prevTermAct, $mvOutAct);
                }

                // Termination: MV cable out term
                $mvOutTerm = $this->createActivity($project, $termRmuToSrWbs,
                    "{$codePrefix}-MOT", "Terminate MV Cable at RMU and Switchroom -{$rmuTag}",
                    self::RMU_DURATIONS['termination'],
                );
                $this->link($project, $mvOutAct, $mvOutTerm);

                // Control: under Electrical > RMU Control and Protection Cabling
                $ctrlAct = $this->createActivity($project, $rmuCtrlWbs,
                    "{$codePrefix}-CTR", "RMU Control and Protection Cabling -{$rmuTag}",
                    self::RMU_DURATIONS['control'],
                );
                $this->link($project, $mechAct, $ctrlAct);

                // Testing: under Mechanical > RMU Testing (no per-RMU completion milestone)
                $testAct = $this->createActivity($project, $rmuTestWbs,
                    "{$codePrefix}-TST", "RMU Functional Testing -{$rmuTag}",
                    self::RMU_DURATIONS['testing'],
                );
                $this->link($project, $mvOutTerm, $testAct);
                $this->link($project, $ctrlAct, $testAct);

                $this->activityMap["rmu_{$z}_{$b}_{$s}_complete"] = $testAct;
                $blockRmuTestActs[] = $testAct;

                $prevTermAct = $mvOutTerm;
            }

            // Block-level RMU rollup milestone (matches Battery/PCS/SUT pattern)
            if (!empty($blockRmuTestActs)) {
                $blockLabel = $block->displayLabel();
                $rmuBlockMs = $this->createMilestone($project, $wbs['block'],
                    "Z{$z}-B{$b}-RMU-BLK",
                    "{$blockLabel} -All RMUs Installation Complete",
                );
                foreach ($blockRmuTestActs as $testAct) {
                    $this->link($project, $testAct, $rmuBlockMs);
                }
                $this->activityMap["rmu_block_{$z}_{$b}_complete"] = $rmuBlockMs;

                $blockComplete = $this->activityMap["block_{$z}_{$b}_complete"] ?? null;
                if ($blockComplete) {
                    $this->link($project, $rmuBlockMs, $blockComplete);
                }
            }
        }
    }

    /**
     * Per-block RMU: one collector RMU per block. All SUTs connect to it.
     */
    private function generatePerBlockRmuActivities(Project $project, $blocks, $nodes, bool $ringSequencing): void
    {
        $prevBlockRmuTerm = null;

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;

            $rmuNode = $nodes->first(fn ($n) =>
                $n->type === EquipmentNodeType::BlockCollectorRmu
                && $n->zone_number === $z && $n->block_number === $b
            );
            if (!$rmuNode) {
                continue;
            }

            $wbs = $this->getBlockDisciplineWbs($project, $block);
            $rmuCivilWbs = $this->ensureWbs($project, $wbs['civil'], 'RMU Foundation', 5);
            $rmuMechWbs = $this->ensureWbs($project, $wbs['mech'], 'RMU Installation', 5);
            $mvSutToRmuWbs = $this->ensureWbs($project, $wbs['elec'], 'MV Cable Installation SUT to RMU', 5);
            $mvRmuToSrWbs = $this->ensureWbs($project, $wbs['elec'], 'MV Cable Installation RMU to Switchroom', 5);
            $rmuCtrlWbs = $this->ensureWbs($project, $wbs['elec'], 'RMU Control and Protection Cabling', 5);
            $termSutToRmuWbs = $this->ensureWbs($project, $wbs['term'], 'Terminate MV Cable at SUT and RMU', 5);
            $termRmuToSrWbs = $this->ensureWbs($project, $wbs['term'], 'Terminate MV Cable at RMU and Switchroom', 5);

            $rmuTag = "Block RMU B{$b}";
            $codePrefix = "Z{$z}-B{$b}-BRMU";

            $sutNodes = $nodes->filter(fn ($n) =>
                $n->type === EquipmentNodeType::Sut
                && $n->zone_number === $z && $n->block_number === $b
            );

            $firstSutComplete = null;
            foreach ($sutNodes as $sn) {
                $firstSutComplete = $this->activityMap["sut_{$z}_{$b}_{$sn->group_number}_complete"] ?? null;
                if ($firstSutComplete) {
                    break;
                }
            }

            // Civil
            $rmuCivilUnitWbs = $this->ensureWbs($project, $rmuCivilWbs, $rmuTag, 6);
            $civilLast = $this->generateRmuCivilActivities($project, $rmuCivilUnitWbs, $codePrefix, $rmuTag, $firstSutComplete);

            // Mechanical
            $mechAct = $this->createActivity($project, $rmuMechWbs,
                "{$codePrefix}-MEC", "Block Collector RMU Installation -{$rmuTag}",
                self::RMU_DURATIONS['mechanical'],
            );
            $this->link($project, $civilLast, $mechAct);

            // MV cables from each SUT to block RMU
            $lastMvIn = $mechAct;
            foreach ($sutNodes->sortBy('group_number') as $sn) {
                $s = $sn->group_number;
                $mvInAct = $this->createActivity($project, $mvSutToRmuWbs,
                    "{$codePrefix}-MVI-S{$s}", "MV Cable Installation SUT {$s} to Block RMU -{$rmuTag}",
                    self::RMU_DURATIONS['mv_cable_in'],
                );
                $this->link($project, $mechAct, $mvInAct);
                $sutComplete = $this->activityMap["sut_{$z}_{$b}_{$s}_complete"] ?? null;
                if ($sutComplete) {
                    $this->linkIfNotExists($project, $sutComplete, $mvInAct);
                }
                $lastMvIn = $mvInAct;
            }

            // MV cable out
            $mvOutAct = $this->createActivity($project, $mvRmuToSrWbs,
                "{$codePrefix}-MVO", "MV Cable Installation Block RMU to Switchroom -{$rmuTag}",
                self::RMU_DURATIONS['mv_cable_out'],
            );
            $this->link($project, $lastMvIn, $mvOutAct);

            if ($ringSequencing && $prevBlockRmuTerm !== null) {
                $this->link($project, $prevBlockRmuTerm, $mvOutAct);
            }

            $mvOutTerm = $this->createActivity($project, $termRmuToSrWbs,
                "{$codePrefix}-MOT", "Terminate MV Cable at Block RMU and Switchroom -{$rmuTag}",
                self::RMU_DURATIONS['termination'],
            );
            $this->link($project, $mvOutAct, $mvOutTerm);

            $ctrlAct = $this->createActivity($project, $rmuCtrlWbs,
                "{$codePrefix}-CTR", "Block RMU Control and Protection Cabling -{$rmuTag}",
                self::RMU_DURATIONS['control'],
            );
            $this->link($project, $mechAct, $ctrlAct);

            $testAct = $this->createActivity($project, $wbs['block'],
                "{$codePrefix}-TST", "Block Collector RMU Functional Testing -{$rmuTag}",
                self::RMU_DURATIONS['testing'],
            );
            $this->link($project, $mvOutTerm, $testAct);
            $this->link($project, $ctrlAct, $testAct);

            $milestone = $this->createMilestone($project, $wbs['block'],
                "{$codePrefix}-COM", "Block Collector RMU B{$b} Complete",
            );
            $this->link($project, $testAct, $milestone);
            $this->activityMap["brmu_{$z}_{$b}_complete"] = $milestone;

            $prevBlockRmuTerm = $mvOutTerm;
        }
    }

    /**
     * Per-zone RMU: one collector RMU per zone.
     */
    private function generatePerZoneRmuActivities(Project $project, $blocks, $nodes, bool $ringSequencing): void
    {
        $zones = $blocks->pluck('zone_number')->unique()->sort();
        $prevZoneRmuTerm = null;

        foreach ($zones as $z) {
            $rmuNode = $nodes->first(fn ($n) =>
                $n->type === EquipmentNodeType::ZoneCollectorRmu && $n->zone_number === $z
            );
            if (!$rmuNode) {
                continue;
            }

            $zoneBlock = $blocks->firstWhere('zone_number', $z);
            $zoneLabel = $zoneBlock->zoneConfiguration->displayLabel();

            $constructionBranch = WbsNode::where('project_id', $project->id)
                ->where('level', 1)
                ->where('wbs_category', WbsCategory::Construction)
                ->firstOrFail();

            $zoneWbs = $this->ensureWbs($project, $constructionBranch, $zoneLabel, 2);
            $rmuWbs = $this->ensureWbs($project, $zoneWbs, 'Zone Collector RMU', 3);

            $rmuTag = "Zone RMU Z{$z}";
            $codePrefix = "Z{$z}-ZRMU";

            $civilLast = $this->generateRmuCivilActivities($project, $rmuWbs, $codePrefix, $rmuTag, null);

            $mechAct = $this->createActivity($project, $rmuWbs,
                "{$codePrefix}-MEC", "Zone Collector RMU Installation -{$rmuTag}",
                self::RMU_DURATIONS['mechanical'],
            );
            $this->link($project, $civilLast, $mechAct);

            $mvOutAct = $this->createActivity($project, $rmuWbs,
                "{$codePrefix}-MVO", "MV Cable Installation Zone RMU to Main Transformer -{$rmuTag}",
                self::RMU_DURATIONS['mv_cable_out'],
            );
            $this->link($project, $mechAct, $mvOutAct);

            if ($ringSequencing && $prevZoneRmuTerm !== null) {
                $this->link($project, $prevZoneRmuTerm, $mvOutAct);
            }

            $mvOutTerm = $this->createActivity($project, $rmuWbs,
                "{$codePrefix}-MOT", "Terminate MV Cable at Zone RMU -{$rmuTag}",
                self::RMU_DURATIONS['termination'],
            );
            $this->link($project, $mvOutAct, $mvOutTerm);

            $ctrlAct = $this->createActivity($project, $rmuWbs,
                "{$codePrefix}-CTR", "Zone RMU Control and Protection Cabling -{$rmuTag}",
                self::RMU_DURATIONS['control'],
            );
            $this->link($project, $mechAct, $ctrlAct);

            $testAct = $this->createActivity($project, $rmuWbs,
                "{$codePrefix}-TST", "Zone Collector RMU Functional Testing -{$rmuTag}",
                self::RMU_DURATIONS['testing'],
            );
            $this->link($project, $mvOutTerm, $testAct);
            $this->link($project, $ctrlAct, $testAct);

            $milestone = $this->createMilestone($project, $rmuWbs,
                "{$codePrefix}-COM", "Zone Collector RMU Z{$z} Complete",
            );
            $this->link($project, $testAct, $milestone);
            $this->activityMap["zrmu_{$z}_complete"] = $milestone;

            $prevZoneRmuTerm = $mvOutTerm;
        }
    }

    /**
     * Two-level: SUT-level RMUs (per_sut) + block collector RMUs.
     */
    private function generateTwoLevelRmuActivities(Project $project, $blocks, $nodes, bool $ringSequencing): void
    {
        $this->generatePerSutRmuActivities($project, $blocks, $nodes, $ringSequencing);

        $prevBlockRmuTerm = null;

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;

            $rmuNode = $nodes->first(fn ($n) =>
                $n->type === EquipmentNodeType::BlockCollectorRmu
                && $n->zone_number === $z && $n->block_number === $b
            );
            if (!$rmuNode) {
                continue;
            }

            $wbs = $this->getBlockDisciplineWbs($project, $block);
            $rmuCivilWbs = $this->ensureWbs($project, $wbs['civil'], 'RMU Foundation', 5);
            $rmuMechWbs = $this->ensureWbs($project, $wbs['mech'], 'RMU Installation', 5);
            $mvSutToRmuWbs = $this->ensureWbs($project, $wbs['elec'], 'MV Cable Installation SUT to RMU', 5);
            $mvRmuToSrWbs = $this->ensureWbs($project, $wbs['elec'], 'MV Cable Installation RMU to Switchroom', 5);
            $rmuCtrlWbs = $this->ensureWbs($project, $wbs['elec'], 'RMU Control and Protection Cabling', 5);
            $termSutToRmuWbs = $this->ensureWbs($project, $wbs['term'], 'Terminate MV Cable at SUT and RMU', 5);
            $termRmuToSrWbs = $this->ensureWbs($project, $wbs['term'], 'Terminate MV Cable at RMU and Switchroom', 5);

            $rmuTag = "Block RMU B{$b}";
            $codePrefix = "Z{$z}-B{$b}-BRMU";

            $sutRmuComplete = null;
            for ($s = 1; $s <= $block->sut_count; $s++) {
                $sutRmuComplete = $this->activityMap["rmu_{$z}_{$b}_{$s}_complete"] ?? null;
                if ($sutRmuComplete) {
                    break;
                }
            }

            $rmuCivilUnitWbs = $this->ensureWbs($project, $rmuCivilWbs, $rmuTag, 6);
            $civilLast = $this->generateRmuCivilActivities($project, $rmuCivilUnitWbs, $codePrefix, $rmuTag, $sutRmuComplete);

            $mechAct = $this->createActivity($project, $rmuMechWbs,
                "{$codePrefix}-MEC", "Block Collector RMU Installation -{$rmuTag}",
                self::RMU_DURATIONS['mechanical'],
            );
            $this->link($project, $civilLast, $mechAct);

            $lastMvIn = $mechAct;
            for ($s = 1; $s <= $block->sut_count; $s++) {
                $rmuComplete = $this->activityMap["rmu_{$z}_{$b}_{$s}_complete"] ?? null;
                $mvInAct = $this->createActivity($project, $mvSutToRmuWbs,
                    "{$codePrefix}-MVI-R{$s}", "MV Cable Installation SUT RMU {$s} to Block RMU -{$rmuTag}",
                    self::RMU_DURATIONS['mv_cable_in'],
                );
                $this->link($project, $mechAct, $mvInAct);
                if ($rmuComplete) {
                    $this->linkIfNotExists($project, $rmuComplete, $mvInAct);
                }
                $lastMvIn = $mvInAct;
            }

            $mvOutAct = $this->createActivity($project, $mvRmuToSrWbs,
                "{$codePrefix}-MVO", "MV Cable Installation Block RMU to Switchroom -{$rmuTag}",
                self::RMU_DURATIONS['mv_cable_out'],
            );
            $this->link($project, $lastMvIn, $mvOutAct);

            if ($ringSequencing && $prevBlockRmuTerm !== null) {
                $this->link($project, $prevBlockRmuTerm, $mvOutAct);
            }

            $mvOutTerm = $this->createActivity($project, $termRmuToSrWbs,
                "{$codePrefix}-MOT", "Terminate MV Cable at Block RMU and Switchroom -{$rmuTag}",
                self::RMU_DURATIONS['termination'],
            );
            $this->link($project, $mvOutAct, $mvOutTerm);

            $ctrlAct = $this->createActivity($project, $rmuCtrlWbs,
                "{$codePrefix}-CTR", "Block RMU Control and Protection Cabling -{$rmuTag}",
                self::RMU_DURATIONS['control'],
            );
            $this->link($project, $mechAct, $ctrlAct);

            $testAct = $this->createActivity($project, $wbs['block'],
                "{$codePrefix}-TST", "Block Collector RMU Functional Testing -{$rmuTag}",
                self::RMU_DURATIONS['testing'],
            );
            $this->link($project, $mvOutTerm, $testAct);
            $this->link($project, $ctrlAct, $testAct);

            $milestone = $this->createMilestone($project, $wbs['block'],
                "{$codePrefix}-COM", "Block Collector RMU B{$b} Complete",
            );
            $this->link($project, $testAct, $milestone);
            $this->activityMap["brmu_{$z}_{$b}_complete"] = $milestone;

            $prevBlockRmuTerm = $mvOutTerm;
        }
    }

    /**
     * Generate the small civil pad activities for an RMU.
     */
    private function generateRmuCivilActivities(
        Project $project,
        WbsNode $wbsNode,
        string $codePrefix,
        string $rmuTag,
        ?ActivityInstance $predecessor,
    ): ActivityInstance {
        $previous = $predecessor;

        foreach (self::RMU_CIVIL_ACTIVITIES as $i => $def) {
            $activity = $this->createActivity(
                $project,
                $wbsNode,
                "{$codePrefix}-{$def['suffix']}",
                "RMU Pad {$def['name']} -{$rmuTag}",
                $def['duration'],
                $i + 1,
            );

            if ($previous) {
                $this->link($project, $previous, $activity);
            }

            $previous = $activity;
        }

        return $previous;
    }

    /**
     * Get or create the discipline WBS nodes for a block.
     * Returns the existing nodes created during the main block loop.
     */
    private function getBlockDisciplineWbs(Project $project, BlockConfiguration $block): array
    {
        $constructionBranch = WbsNode::where('project_id', $project->id)
            ->where('level', 1)
            ->where('wbs_category', WbsCategory::Construction)
            ->firstOrFail();

        $zoneLabel = $block->zoneConfiguration->displayLabel();
        $blockLabel = $block->displayLabel();
        $zoneWbs = $this->ensureWbs($project, $constructionBranch, $zoneLabel, 2);
        $blockWbs = $this->ensureWbs($project, $zoneWbs, $blockLabel, 3);

        return [
            'block' => $blockWbs,
            'civil' => $this->ensureWbs($project, $blockWbs, 'Civil Works', 4),
            'mech'  => $this->ensureWbs($project, $blockWbs, 'Mechanical', 4),
            'elec'  => $this->ensureWbs($project, $blockWbs, 'Electrical Cable Installation', 4),
            'term'  => $this->ensureWbs($project, $blockWbs, 'Termination', 4),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Generate block-level BESS Pre-Commissioning activities. One FS-chained
     * sequence of test activities per block under Commissioning > BESS
     * Pre-Commissioning > Block N. Entry gates on block construction
     * complete + ITP IFC. Sign-off feeds First BESS Energisation.
     */
    private function generatePerBlockBessPreCommActivities(Project $project, $blocks): void
    {
        if ($blocks->isEmpty()) {
            return;
        }

        $commissioningBranch = WbsNode::where('project_id', $project->id)
            ->where('level', 1)
            ->where('wbs_category', WbsCategory::Commissioning)
            ->first();
        if ($commissioningBranch === null) {
            return;
        }

        $bessPreCommWbs = WbsNode::firstOrCreate(
            [
                'project_id' => $project->id,
                'parent_id' => $commissioningBranch->id,
                'name' => 'BESS Pre-Commissioning',
            ],
            [
                'code' => $commissioningBranch->code . '.' . ($commissioningBranch->children()->count() + 1),
                'level' => 2,
                'sort_order' => $commissioningBranch->children()->count() + 1,
                'wbs_category' => WbsCategory::Commissioning,
            ]
        );

        $itpIfc = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'itp_ifc'))
            ->where('is_milestone', true)
            ->first();

        $firstEnergise = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'ms_first_bess_energise'))
            ->first();

        $activityDefs = [
            ['suffix' => 'DCIR', 'name' => 'DC IR, Polarity and Continuity Tests (strings and DC cables)', 'duration' => 2],
            ['suffix' => 'BAL',  'name' => 'Battery String Voltage and Balance Check',                     'duration' => 1],
            ['suffix' => 'BMS',  'name' => 'BMS Commissioning (comms, alarms, SOC reporting)',             'duration' => 2],
            ['suffix' => 'PCSP', 'name' => 'PCS Pre-Energisation Tests (IR, parameter load, firmware)',    'duration' => 2],
            ['suffix' => 'DCBF', 'name' => 'DC Bus, Contactor and Fuse Function Tests',                    'duration' => 1],
            ['suffix' => 'EBC',  'name' => 'Earth Bonding Continuity at Containers',                       'duration' => 1],
            ['suffix' => 'HVAC', 'name' => 'Container HVAC Functional Test',                               'duration' => 1],
            ['suffix' => 'FIRE', 'name' => 'Container Fire Detection and Suppression Functional Test',     'duration' => 1],
            ['suffix' => 'WALK', 'name' => 'Pre-Energisation Safety Walkdown',                             'duration' => 1],
            ['suffix' => 'PUNC', 'name' => 'Punch List Closeout',                                          'duration' => 2],
        ];

        $previousBlockSignoffByZone = [];

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;
            $blockLabel = $block->displayLabel();
            $zoneLabel = $block->zoneConfiguration->displayLabel();

            $zoneWbs = WbsNode::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'parent_id' => $bessPreCommWbs->id,
                    'name' => $zoneLabel,
                ],
                [
                    'code' => $bessPreCommWbs->code . '.' . ($bessPreCommWbs->children()->count() + 1),
                    'level' => 3,
                    'sort_order' => $bessPreCommWbs->children()->count() + 1,
                    'wbs_category' => WbsCategory::Commissioning,
                ]
            );

            $blockWbs = WbsNode::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'parent_id' => $zoneWbs->id,
                    'name' => $blockLabel,
                ],
                [
                    'code' => $zoneWbs->code . '.' . ($zoneWbs->children()->count() + 1),
                    'level' => 4,
                    'sort_order' => $zoneWbs->children()->count() + 1,
                    'wbs_category' => WbsCategory::Commissioning,
                ]
            );

            $codePrefix = "Z{$z}-B{$b}-BPC";
            $blockComplete = $this->activityMap["block_{$z}_{$b}_complete"] ?? null;
            $previousBlockSignoff = $previousBlockSignoffByZone[$z] ?? null;

            $previous = null;
            foreach ($activityDefs as $def) {
                $act = $this->createActivity(
                    $project,
                    $blockWbs,
                    "{$codePrefix}-{$def['suffix']}",
                    "{$blockLabel} BESS Pre-Comm — {$def['name']}",
                    $def['duration'],
                );
                if ($previous === null) {
                    if ($blockComplete) {
                        $this->link($project, $blockComplete, $act);
                    }
                    // Cascade within zone: subsequent blocks gate on the
                    // previous block's pre-comm sign-off (which transitively
                    // carries the ITP IFC gate). First block in each zone
                    // still gates directly on ITP IFC.
                    if ($previousBlockSignoff !== null) {
                        $this->linkIfNotExists($project, $previousBlockSignoff, $act);
                    } elseif ($itpIfc) {
                        $this->linkIfNotExists($project, $itpIfc, $act);
                    }
                } else {
                    $this->link($project, $previous, $act);
                }
                $previous = $act;
            }

            $signoff = $this->createMilestone(
                $project,
                $blockWbs,
                "{$codePrefix}-SO",
                "{$blockLabel} BESS Pre-Commissioning Sign-Off",
            );
            if ($previous !== null) {
                $this->link($project, $previous, $signoff);
            }
            $this->activityMap["bess_precomm_{$z}_{$b}_signoff"] = $signoff;
            $previousBlockSignoffByZone[$z] = $signoff;

            if ($firstEnergise !== null) {
                $this->linkIfNotExists($project, $signoff, $firstEnergise);
            }
        }
    }

    /**
     * Generate block-level Live Energisation activities under
     * Commissioning > BESS Live Energisation > Zone N > Block N.
     *
     * First block of each zone gates on First BESS Energisation; subsequent
     * blocks cascade on the previous block's live-energisation sign-off.
     * Each block's sign-off feeds BESS Ready for Online Commissioning. The
     * direct First-Energise → Online-Comm-Ready edge is removed since the
     * new path provides the dependency.
     */
    private function generatePerBlockBessLiveEnergisationActivities(Project $project, $blocks): void
    {
        if ($blocks->isEmpty()) {
            return;
        }

        $commissioningBranch = WbsNode::where('project_id', $project->id)
            ->where('level', 1)
            ->where('wbs_category', WbsCategory::Commissioning)
            ->first();
        if ($commissioningBranch === null) {
            return;
        }

        $liveEnergiseWbs = WbsNode::firstOrCreate(
            [
                'project_id' => $project->id,
                'parent_id' => $commissioningBranch->id,
                'name' => 'BESS Live Energisation',
            ],
            [
                'code' => $commissioningBranch->code . '.' . ($commissioningBranch->children()->count() + 1),
                'level' => 2,
                'sort_order' => $commissioningBranch->children()->count() + 1,
                'wbs_category' => WbsCategory::Commissioning,
            ]
        );

        $firstEnergise = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'ms_first_bess_energise'))
            ->first();

        $onlineCommReady = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'ms_bess_online_comm'))
            ->first();

        $activityDefs = [
            ['suffix' => 'SOFT', 'name' => 'DC Bus Soft Charge / Pre-Charge Sequence',                          'duration' => 1],
            ['suffix' => 'DCEN', 'name' => 'First DC Bus Energisation',                                          'duration' => 1],
            ['suffix' => 'ACEN', 'name' => 'PCS AC Initial Energisation',                                        'duration' => 1],
            ['suffix' => 'SYNC', 'name' => 'PCS Synchronisation and Phasing Check',                              'duration' => 1],
            ['suffix' => 'CHRG', 'name' => 'Initial Charge / Discharge Cycle and Voltage Sensing Verification',  'duration' => 2],
            ['suffix' => 'LIVE', 'name' => 'Live Protection and SCADA Verification',                             'duration' => 1],
            ['suffix' => 'PUNC', 'name' => 'Live Energisation Punch List Closeout',                              'duration' => 1],
        ];

        $previousBlockSignoffByZone = [];

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;
            $blockLabel = $block->displayLabel();
            $zoneLabel = $block->zoneConfiguration->displayLabel();

            $zoneWbs = WbsNode::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'parent_id' => $liveEnergiseWbs->id,
                    'name' => $zoneLabel,
                ],
                [
                    'code' => $liveEnergiseWbs->code . '.' . ($liveEnergiseWbs->children()->count() + 1),
                    'level' => 3,
                    'sort_order' => $liveEnergiseWbs->children()->count() + 1,
                    'wbs_category' => WbsCategory::Commissioning,
                ]
            );

            $blockWbs = WbsNode::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'parent_id' => $zoneWbs->id,
                    'name' => $blockLabel,
                ],
                [
                    'code' => $zoneWbs->code . '.' . ($zoneWbs->children()->count() + 1),
                    'level' => 4,
                    'sort_order' => $zoneWbs->children()->count() + 1,
                    'wbs_category' => WbsCategory::Commissioning,
                ]
            );

            $codePrefix = "Z{$z}-B{$b}-BLE";
            $previousBlockSignoff = $previousBlockSignoffByZone[$z] ?? null;

            $previous = null;
            foreach ($activityDefs as $def) {
                $act = $this->createActivity(
                    $project,
                    $blockWbs,
                    "{$codePrefix}-{$def['suffix']}",
                    "{$blockLabel} BESS Live Energisation — {$def['name']}",
                    $def['duration'],
                );
                if ($previous === null) {
                    // First block of each zone gates on First BESS Energisation.
                    // Subsequent blocks cascade on previous block's sign-off
                    // (which transitively carries the First Energise gate).
                    if ($previousBlockSignoff !== null) {
                        $this->linkIfNotExists($project, $previousBlockSignoff, $act);
                    } elseif ($firstEnergise !== null) {
                        $this->linkIfNotExists($project, $firstEnergise, $act);
                    }
                } else {
                    $this->link($project, $previous, $act);
                }
                $previous = $act;
            }

            $signoff = $this->createMilestone(
                $project,
                $blockWbs,
                "{$codePrefix}-SO",
                "{$blockLabel} BESS Live Energisation Sign-Off",
            );
            if ($previous !== null) {
                $this->link($project, $previous, $signoff);
            }
            $this->activityMap["bess_live_{$z}_{$b}_signoff"] = $signoff;
            $previousBlockSignoffByZone[$z] = $signoff;

            if ($onlineCommReady !== null) {
                $this->linkIfNotExists($project, $signoff, $onlineCommReady);
            }
        }

        // Remove the now-redundant direct edge — the new chain provides the
        // dependency through every block's live-energisation sign-off.
        if ($firstEnergise !== null && $onlineCommReady !== null) {
            Relationship::where('project_id', $project->id)
                ->where('predecessor_id', $firstEnergise->id)
                ->where('successor_id', $onlineCommReady->id)
                ->delete();
        }
    }

    /**
     * Generate project-level Online Commissioning activities under
     * Commissioning > BESS Online Commissioning. FS chain from BESS Ready
     * for Online Commissioning to Hold Point 1 Released. Removes the
     * redundant Online-Comm-Ready → Hold-Point-1 direct edge.
     */
    private function generateBessOnlineCommissioningActivities(Project $project): void
    {
        $commissioningBranch = WbsNode::where('project_id', $project->id)
            ->where('level', 1)
            ->where('wbs_category', WbsCategory::Commissioning)
            ->first();
        if ($commissioningBranch === null) {
            return;
        }

        $onlineCommReady = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'ms_bess_online_comm'))
            ->first();

        $holdPoint1 = ActivityInstance::where('project_id', $project->id)
            ->whereHas('packageInstance.template', fn ($q) => $q->where('code', 'ms_hold_point_1'))
            ->first();

        if ($onlineCommReady === null || $holdPoint1 === null) {
            return;
        }

        $onlineCommWbs = WbsNode::firstOrCreate(
            [
                'project_id' => $project->id,
                'parent_id' => $commissioningBranch->id,
                'name' => 'BESS Online Commissioning',
            ],
            [
                'code' => $commissioningBranch->code . '.' . ($commissioningBranch->children()->count() + 1),
                'level' => 2,
                'sort_order' => $commissioningBranch->children()->count() + 1,
                'wbs_category' => WbsCategory::Commissioning,
            ]
        );

        $activityDefs = [
            ['suffix' => 'CAP',  'name' => 'Capacity Test (full charge / discharge cycle)',          'duration' => 3],
            ['suffix' => 'RTE',  'name' => 'Round-Trip Efficiency Test',                              'duration' => 2],
            ['suffix' => 'RAMP', 'name' => 'Ramp-Rate and Response-Time Tests',                       'duration' => 2],
            ['suffix' => 'GRID', 'name' => 'Grid Services and Frequency Response Tests',              'duration' => 3],
            ['suffix' => 'REL',  'name' => 'Reliability Run (continuous operation)',                  'duration' => 14],
            ['suffix' => 'PERF', 'name' => 'Performance Guarantee Tests',                             'duration' => 3],
            ['suffix' => 'SAT',  'name' => 'Site Acceptance Test (SAT)',                              'duration' => 3],
            ['suffix' => 'PUNC', 'name' => 'Online Commissioning Punch List Closeout',               'duration' => 2],
            ['suffix' => 'DOC',  'name' => 'Handover Documentation Review and Signoff',              'duration' => 2],
        ];

        $codePrefix = 'BOC';
        $previous = $onlineCommReady;
        foreach ($activityDefs as $def) {
            $act = $this->createActivity(
                $project,
                $onlineCommWbs,
                "{$codePrefix}-{$def['suffix']}",
                "BESS Online Commissioning — {$def['name']}",
                $def['duration'],
            );
            $this->link($project, $previous, $act);
            $previous = $act;
        }

        $this->linkIfNotExists($project, $previous, $holdPoint1);

        // Remove the now-redundant direct edge — the new chain provides
        // the dependency through the online commissioning activities.
        Relationship::where('project_id', $project->id)
            ->where('predecessor_id', $onlineCommReady->id)
            ->where('successor_id', $holdPoint1->id)
            ->delete();
    }

    private function createActivity(
        Project $project,
        WbsNode $wbsNode,
        string $activityCode,
        string $name,
        int $duration,
        int $sequence = 1,
    ): ActivityInstance {
        return ActivityInstance::create([
            'project_id' => $project->id,
            'wbs_node_id' => $wbsNode->id,
            'activity_code' => $activityCode,
            'name' => $name,
            'duration_days' => $duration,
            'is_milestone' => false,
            'sequence' => $sequence,
            'p6_task_type' => P6TaskType::Task,
        ]);
    }

    private function createMilestone(
        Project $project,
        WbsNode $wbsNode,
        string $activityCode,
        string $name,
    ): ActivityInstance {
        return ActivityInstance::create([
            'project_id' => $project->id,
            'wbs_node_id' => $wbsNode->id,
            'activity_code' => $activityCode,
            'name' => $name,
            'duration_days' => 0,
            'is_milestone' => true,
            'sequence' => 1,
            'p6_task_type' => P6TaskType::FinishMilestone,
        ]);
    }

    private function link(
        Project $project,
        ActivityInstance $predecessor,
        ActivityInstance $successor,
        RelationshipType $type = RelationshipType::FinishToStart,
        int $lagDays = 0,
    ): void {
        Relationship::create([
            'project_id' => $project->id,
            'predecessor_id' => $predecessor->id,
            'successor_id' => $successor->id,
            'relationship_type' => $type,
            'lag_days' => $lagDays,
            'origin' => RelationshipOrigin::Internal,
        ]);
    }

    /**
     * Link the current block's Workfront Released milestone to the previous
     * block in the same zone, per the project-level sequencing mode.
     *
     * - FS:     previous block's civil-complete (FS, 0)
     * - SS:     previous block's workfront     (SS, 0)
     * - SS_LAG: previous block's workfront     (SS, lagDays)
     */
    private function linkBlockToPrevious(
        Project $project,
        ActivityInstance $workfrontMs,
        ?ActivityInstance $previousCivilComplete,
        ?ActivityInstance $previousWorkfront,
        BlockSequencingMode $mode,
        int $lagDays,
    ): void {
        $predecessor = $mode === BlockSequencingMode::FinishToStart
            ? $previousCivilComplete
            : $previousWorkfront;

        if ($predecessor === null) {
            return; // first block in the zone — no predecessor
        }

        $this->link(
            $project,
            $predecessor,
            $workfrontMs,
            $mode->relationshipType(),
            $mode->requiresLag() ? max(0, $lagDays) : 0,
        );
    }

    private function linkIfNotExists(Project $project, ActivityInstance $predecessor, ActivityInstance $successor): void
    {
        $exists = Relationship::where('predecessor_id', $predecessor->id)
            ->where('successor_id', $successor->id)
            ->exists();

        if (!$exists) {
            $this->link($project, $predecessor, $successor);
        }
    }

    private function ensureWbs(Project $project, WbsNode $parent, string $name, int $level): WbsNode
    {
        return WbsNode::firstOrCreate(
            [
                'project_id' => $project->id,
                'parent_id' => $parent->id,
                'name' => $name,
            ],
            [
                'code' => $parent->code . '.' . ($parent->children()->count() + 1),
                'level' => $level,
                'sort_order' => $parent->children()->count() + 1,
                'wbs_category' => WbsCategory::Construction,
            ]
        );
    }

    private function scaleDuration(int $baseDuration, float $ratio): int
    {
        if ($ratio >= 1.0) {
            return $baseDuration;
        }

        return max(1, (int) round($baseDuration * $ratio));
    }
}