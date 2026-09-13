<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ValidationSeverity;
use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\Relationship;

/**
 * Critical: Every activity must have at least one successor
 * (unless it is a terminal milestone like project completion or handover).
 */
class OpenFinishRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        $allActivities = ActivityInstance::where('project_id', $project->id)->get();
        $predecessorIds = Relationship::where('project_id', $project->id)
            ->pluck('predecessor_id')
            ->unique();

        $errors = [];

        foreach ($allActivities as $activity) {
            if ($predecessorIds->contains($activity->id)) {
                continue;
            }

            // Terminal milestones (handover, hold point release) are allowed to have open finishes
            // These are identified as the final milestone in the energisation chain
            if ($this->isTerminalMilestone($activity)) {
                continue;
            }

            $errors[] = new ValidationResult(
                ruleClass: self::class,
                severity: ValidationSeverity::Critical,
                message: "Activity '{$activity->name}' ({$activity->activity_code}) has no successor (open finish).",
                context: [
                    'activity_instance_id' => $activity->id,
                    'activity_code' => $activity->activity_code,
                ],
            );
        }

        return $errors;
    }

    private function isTerminalMilestone(ActivityInstance $activity): bool
    {
        if (!$activity->is_milestone) {
            return false;
        }

        $terminalCodes = ['ms_hold_point_1', 'ms_bess_online_comm'];
        $packageCode = $activity->packageInstance?->template?->code;

        return in_array($packageCode, $terminalCodes, true);
    }
}