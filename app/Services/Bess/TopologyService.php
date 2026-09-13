<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\BessArchitecture;
use App\Enums\EquipmentNodeType;
use App\Enums\RmuTopology;
use App\Models\BlockConfiguration;
use App\Models\EquipmentNode;
use App\Models\Project;
use App\Models\ZoneConfiguration;
use Illuminate\Support\Collection;

class TopologyService
{
    /**
     * Initialize zone configurations from project-level defaults.
     * Creates zone_configurations and block_configurations records
     * with uniform defaults that the user can then customize.
     */
    public function initializeFromDefaults(Project $project): void
    {
        // Clear existing topology config
        ZoneConfiguration::where('project_id', $project->id)->delete();

        $arch = $project->bess_architecture;
        $hasSeparatePcs = $arch === null || $arch->generatesPcsInstall();
        $hasSut = $arch === null || $arch->generatesSut();

        $defaultGroupSize = $project->batteries_per_group;
        $defaultBatteriesPerBlock = $defaultGroupSize * $project->battery_groups_per_block;

        // PCS and SUT counts: zero when architecture suppresses them
        $pcsCount = $hasSeparatePcs
            ? ($project->pcs_per_group ?? 2) * ($project->pcs_groups_per_block ?? 5)
            : 0;
        $pcsGroupSize = $hasSeparatePcs ? ($project->pcs_per_group ?? 2) : 0;
        $sutCount = $hasSut ? ($project->suts_per_block ?? 2) : 0;

        for ($z = 1; $z <= $project->zone_count; $z++) {
            $zone = ZoneConfiguration::create([
                'project_id' => $project->id,
                'zone_number' => $z,
                'zone_label' => "Zone {$z}",
                'block_count' => $project->blocks_per_zone,
            ]);

            for ($b = 1; $b <= $project->blocks_per_zone; $b++) {
                $block = BlockConfiguration::create([
                    'project_id' => $project->id,
                    'zone_configuration_id' => $zone->id,
                    'zone_number' => $z,
                    'block_number' => $b,
                    'block_label' => "Block {$b}",
                    'battery_count' => $defaultBatteriesPerBlock,
                    'battery_group_size' => $defaultGroupSize,
                    'pcs_count' => $pcsCount,
                    'pcs_group_size' => $pcsGroupSize,
                    'sut_count' => $sutCount,
                ]);

                $block->recalculate();
            }

            $zone->recalculateTotals();
        }
    }

    /**
     * Update a single block configuration and recalculate.
     */
    public function updateBlock(BlockConfiguration $block, array $data): BlockConfiguration
    {
        $block->update($data);
        $block->recalculate();
        $block->zoneConfiguration->recalculateTotals();

        return $block->fresh();
    }

    /**
     * Accept a partial group for a block.
     */
    public function acceptBatteryPartial(BlockConfiguration $block): void
    {
        $block->update(['battery_remainder_decision' => 'accepted']);
        $block->recalculate();
    }

    public function acceptPcsPartial(BlockConfiguration $block): void
    {
        $block->update(['pcs_remainder_decision' => 'accepted']);
        $block->recalculate();
    }

    /**
     * Validate total battery count matches project total.
     * Returns null if valid, or an error message.
     */
    public function validateBatteryTotal(Project $project): ?string
    {
        if ($project->total_battery_count === null) {
            return null; // No total set, skip validation
        }

        $configuredTotal = BlockConfiguration::where('project_id', $project->id)
            ->sum('battery_count');

        if ((int) $configuredTotal !== $project->total_battery_count) {
            $diff = $project->total_battery_count - (int) $configuredTotal;

            return "Total batteries configured: {$configuredTotal}. "
                . "Project total: {$project->total_battery_count}. "
                . "Difference: {$diff} batteries.";
        }

        return null;
    }

    /**
     * Check if all blocks have resolved their remainder decisions.
     */
    public function hasUnresolvedRemainders(Project $project): bool
    {
        return BlockConfiguration::where('project_id', $project->id)
            ->where('is_configured', false)
            ->exists();
    }

    /**
     * Generate equipment nodes from the per-block configuration.
     * Replaces the flat topology generation in GraphCompilerService.
     */
    public function generateEquipmentNodes(Project $project): void
    {
        EquipmentNode::where('project_id', $project->id)->delete();

        $arch = $project->bess_architecture;
        $hasSeparatePcs = $arch === null || $arch->generatesPcsInstall();
        $hasSut = $arch === null || $arch->generatesSut();
        $unitLabel = ($arch === BessArchitecture::IntegratedContainer)
            ? ($project->container_unit_label ?? 'Battery Container')
            : 'Battery';

        $blocks = BlockConfiguration::where('project_id', $project->id)
            ->with('zoneConfiguration')
            ->orderBy('zone_number')
            ->orderBy('block_number')
            ->get();

        // Track continuous numbering across all blocks
        $globalBatteryCounter = 1;
        $globalPcsCounter = 1;

        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;
            $zoneLabel = $block->zoneConfiguration->displayLabel();
            $blockLabel = $block->displayLabel();

            // ── Battery / Container Groups ───────────────────
            $groupSize = $block->battery_group_size;
            $totalGroups = $block->totalBatteryGroups();

            for ($g = 1; $g <= $totalGroups; $g++) {
                $isPartial = ($g > $block->full_battery_group_count) && $block->has_battery_partial_group;
                $unitsInGroup = $isPartial ? $block->battery_remainder : $groupSize;
                $unitFrom = $globalBatteryCounter;
                $unitTo = $globalBatteryCounter + $unitsInGroup - 1;

                $label = "{$zoneLabel} {$blockLabel} {$unitLabel} Group {$g} ({$unitFrom}-{$unitTo})";
                if ($isPartial) {
                    $label .= ' — Partial';
                }

                EquipmentNode::create([
                    'project_id' => $project->id,
                    'type' => EquipmentNodeType::BatteryGroup,
                    'zone_number' => $z,
                    'block_number' => $b,
                    'group_number' => $g,
                    'label' => $label,
                    'unit_from' => $unitFrom,
                    'unit_to' => $unitTo,
                    'is_partial' => $isPartial,
                    'actual_unit_count' => $unitsInGroup,
                ]);

                $globalBatteryCounter = $unitTo + 1;
            }

            // ── PCS Groups — only when architecture has separate PCS ──
            if ($hasSeparatePcs && $block->pcs_count > 0) {
                $pcsGroupSize = $block->pcs_group_size;
                $totalPcsGroups = $block->totalPcsGroups();

                for ($g = 1; $g <= $totalPcsGroups; $g++) {
                    $isPartial = ($g > $block->full_pcs_group_count) && $block->has_pcs_partial_group;
                    $unitsInGroup = $isPartial ? $block->pcs_remainder : $pcsGroupSize;
                    $unitFrom = $globalPcsCounter;
                    $unitTo = $globalPcsCounter + $unitsInGroup - 1;

                    $label = "{$zoneLabel} {$blockLabel} PCS Group {$g} ({$unitFrom}-{$unitTo})";
                    if ($isPartial) {
                        $label .= ' — Partial';
                    }

                    EquipmentNode::create([
                        'project_id' => $project->id,
                        'type' => EquipmentNodeType::PcsGroup,
                        'zone_number' => $z,
                        'block_number' => $b,
                        'group_number' => $g,
                        'label' => $label,
                        'unit_from' => $unitFrom,
                        'unit_to' => $unitTo,
                        'is_partial' => $isPartial,
                        'actual_unit_count' => $unitsInGroup,
                    ]);

                    $globalPcsCounter = $unitTo + 1;
                }
            }

            // ── SUTs — only when architecture has SUTs ───────
            if ($hasSut && $block->sut_count > 0) {
                for ($s = 1; $s <= $block->sut_count; $s++) {
                    EquipmentNode::create([
                        'project_id' => $project->id,
                        'type' => EquipmentNodeType::Sut,
                        'zone_number' => $z,
                        'block_number' => $b,
                        'group_number' => $s,
                        'label' => "{$zoneLabel} {$blockLabel} SUT {$s}",
                    ]);
                }
            }
        }

        // ── RMU nodes ───────────────────────────────────────
        $rmuTopology = $project->rmu_topology;
        if ($rmuTopology !== null && $rmuTopology->generatesRmu() && $hasSut) {
            $this->generateRmuNodes($project, $blocks, $rmuTopology);
        }

        // ── Shared infrastructure nodes ──────────────────────
        if ($project->switchroom_exists) {
            EquipmentNode::create([
                'project_id' => $project->id,
                'type' => EquipmentNodeType::Switchroom,
                'label' => 'Switchroom',
            ]);
        }

        if ($project->control_room_exists) {
            EquipmentNode::create([
                'project_id' => $project->id,
                'type' => EquipmentNodeType::ControlRoom,
                'label' => 'Control Room',
            ]);
        }

        if ($project->transformer_exists) {
            EquipmentNode::create([
                'project_id' => $project->id,
                'type' => EquipmentNodeType::Transformer,
                'label' => 'Main Transformer',
            ]);
        }

        if ($project->substation_exists) {
            EquipmentNode::create([
                'project_id' => $project->id,
                'type' => EquipmentNodeType::Substation,
                'label' => 'Substation',
            ]);
        }
    }

    /**
     * Get topology summary for display.
     */
    public function getSummary(Project $project): array
    {
        $zones = ZoneConfiguration::where('project_id', $project->id)
            ->with('blockConfigurations')
            ->orderBy('zone_number')
            ->get();

        $totalBatteries = 0;
        $totalGroups = 0;
        $totalPcs = 0;
        $totalSuts = 0;
        $hasUnresolved = false;

        $zoneSummaries = [];

        foreach ($zones as $zone) {
            $zoneData = [
                'label' => $zone->displayLabel(),
                'blocks' => [],
                'total_batteries' => 0,
                'total_groups' => 0,
            ];

            foreach ($zone->blockConfigurations as $block) {
                $zoneData['blocks'][] = [
                    'label' => $block->displayLabel(),
                    'battery_count' => $block->battery_count,
                    'group_size' => $block->battery_group_size,
                    'full_groups' => $block->full_battery_group_count,
                    'has_partial' => $block->has_battery_partial_group,
                    'remainder' => $block->battery_remainder,
                    'battery_decision' => $block->battery_remainder_decision,
                    'pcs_count' => $block->pcs_count,
                    'sut_count' => $block->sut_count,
                    'is_configured' => $block->is_configured,
                ];

                $zoneData['total_batteries'] += $block->battery_count;
                $zoneData['total_groups'] += $block->totalBatteryGroups();
                $totalPcs += $block->pcs_count;
                $totalSuts += $block->sut_count;

                if (!$block->is_configured) {
                    $hasUnresolved = true;
                }
            }

            $totalBatteries += $zoneData['total_batteries'];
            $totalGroups += $zoneData['total_groups'];
            $zoneSummaries[] = $zoneData;
        }

        return [
            'zones' => $zoneSummaries,
            'total_batteries' => $totalBatteries,
            'total_groups' => $totalGroups,
            'total_pcs' => $totalPcs,
            'total_suts' => $totalSuts,
            'target_batteries' => $project->total_battery_count,
            'batteries_match' => $project->total_battery_count === null || $totalBatteries === $project->total_battery_count,
            'has_unresolved' => $hasUnresolved,
        ];
    }

    // ── RMU Node Generation ─────────────────────────────────

    private function generateRmuNodes(Project $project, Collection $blocks, RmuTopology $topology): void
    {
        $rmuCounter = 1;

        match ($topology) {
            RmuTopology::PerSut => $this->generatePerSutRmu($project, $blocks, $rmuCounter),
            RmuTopology::PerBlock => $this->generatePerBlockRmu($project, $blocks, $rmuCounter),
            RmuTopology::PerZone => $this->generatePerZoneRmu($project, $blocks),
            RmuTopology::PerSutAndBlock => $this->generateTwoLevelRmu($project, $blocks, $rmuCounter),
            default => null,
        };
    }

    /**
     * One RMU per SUT — placed at each SUT output.
     */
    private function generatePerSutRmu(Project $project, Collection $blocks, int &$counter): void
    {
        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;
            $zoneLabel = $block->zoneConfiguration->displayLabel();
            $blockLabel = $block->displayLabel();

            for ($s = 1; $s <= $block->sut_count; $s++) {
                EquipmentNode::create([
                    'project_id' => $project->id,
                    'type' => EquipmentNodeType::Rmu,
                    'zone_number' => $z,
                    'block_number' => $b,
                    'group_number' => $s,
                    'label' => "{$zoneLabel} {$blockLabel} RMU {$counter} (SUT {$s})",
                ]);
                $counter++;
            }
        }
    }

    /**
     * One collector RMU per block — aggregates all SUTs in the block.
     */
    private function generatePerBlockRmu(Project $project, Collection $blocks, int &$counter): void
    {
        foreach ($blocks as $block) {
            $z = $block->zone_number;
            $b = $block->block_number;
            $zoneLabel = $block->zoneConfiguration->displayLabel();
            $blockLabel = $block->displayLabel();

            EquipmentNode::create([
                'project_id' => $project->id,
                'type' => EquipmentNodeType::BlockCollectorRmu,
                'zone_number' => $z,
                'block_number' => $b,
                'group_number' => 1,
                'label' => "{$zoneLabel} {$blockLabel} Block Collector RMU {$counter}",
            ]);
            $counter++;
        }
    }

    /**
     * One collector RMU per zone.
     */
    private function generatePerZoneRmu(Project $project, Collection $blocks): void
    {
        $zones = $blocks->pluck('zone_number')->unique()->sort();

        foreach ($zones as $z) {
            $zoneLabel = $blocks->firstWhere('zone_number', $z)->zoneConfiguration->displayLabel();

            EquipmentNode::create([
                'project_id' => $project->id,
                'type' => EquipmentNodeType::ZoneCollectorRmu,
                'zone_number' => $z,
                'block_number' => null,
                'group_number' => 1,
                'label' => "{$zoneLabel} Zone Collector RMU",
            ]);
        }
    }

    /**
     * Two-level: SUT-level RMUs feed into block collector RMUs.
     */
    private function generateTwoLevelRmu(Project $project, Collection $blocks, int &$counter): void
    {
        // First level: one RMU per SUT
        $this->generatePerSutRmu($project, $blocks, $counter);

        // Second level: one block collector per block
        $blockCounter = 1;
        $this->generatePerBlockRmu($project, $blocks, $blockCounter);
    }
}