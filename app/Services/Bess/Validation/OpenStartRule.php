<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ConstraintType;
use App\Enums\ValidationSeverity;
use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\Relationship;

/**
 * Critical: Every activity must have at least one predecessor (or a start constraint).
 */
class OpenStartRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        $allActivities = ActivityInstance::where('project_id', $project->id)->get();
        $successorIds = Relationship::where('project_id', $project->id)
            ->pluck('successor_id')
            ->unique();

        $errors = [];

        foreach ($allActivities as $activity) {
            $hasPredecessor = $successorIds->contains($activity->id);
            $hasConstraint = $activity->constraint_type !== ConstraintType::None
                && $activity->constraint_type !== null;

            if (!$hasPredecessor && !$hasConstraint) {
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Critical,
                    message: "Activity '{$activity->name}' ({$activity->activity_code}) has no predecessor and no start constraint (open start).",
                    context: [
                        'activity_instance_id' => $activity->id,
                        'activity_code' => $activity->activity_code,
                    ],
                );
            }
        }

        return $errors;
    }
}