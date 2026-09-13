<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ValidationSeverity;
use App\Models\BlockConfiguration;
use App\Models\Project;

/**
 * Critical: Sum of all block battery counts must equal project.total_battery_count.
 * Also validates that no block has a pending remainder decision.
 */
class BatteryCountReconciliationRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        $errors = [];

        // Check total battery count reconciliation
        if ($project->total_battery_count !== null) {
            $configuredTotal = (int) BlockConfiguration::where('project_id', $project->id)
                ->sum('battery_count');

            if ($configuredTotal !== $project->total_battery_count) {
                $diff = $project->total_battery_count - $configuredTotal;
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Critical,
                    message: "Total batteries configured ({$configuredTotal}) does not match project total ({$project->total_battery_count}). Difference: {$diff} batteries.",
                    context: [
                        'configured_total' => $configuredTotal,
                        'project_total' => $project->total_battery_count,
                        'difference' => $diff,
                    ],
                );
            }
        }

        // Check for unresolved remainder decisions
        $pendingBlocks = BlockConfiguration::where('project_id', $project->id)
            ->where('is_configured', false)
            ->get();

        foreach ($pendingBlocks as $block) {
            if ($block->has_battery_partial_group && $block->battery_remainder_decision === 'pending') {
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Critical,
                    message: "Zone {$block->zone_number} Block {$block->block_number} has an unresolved battery partial group ({$block->battery_remainder} batteries).",
                    context: [
                        'zone_number' => $block->zone_number,
                        'block_number' => $block->block_number,
                        'remainder' => $block->battery_remainder,
                    ],
                );
            }

            if ($block->has_pcs_partial_group && $block->pcs_remainder_decision === 'pending') {
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Critical,
                    message: "Zone {$block->zone_number} Block {$block->block_number} has an unresolved PCS partial group ({$block->pcs_remainder} PCS units).",
                    context: [
                        'zone_number' => $block->zone_number,
                        'block_number' => $block->block_number,
                        'remainder' => $block->pcs_remainder,
                    ],
                );
            }
        }

        return $errors;
    }
}