<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ValidationSeverity;
use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\Relationship;

/**
 * Critical: No milestone may have zero predecessors AND zero successors.
 */
class OrphanMilestoneRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        $milestones = ActivityInstance::where('project_id', $project->id)
            ->where('is_milestone', true)
            ->get();

        $predecessorIds = Relationship::where('project_id', $project->id)
            ->pluck('predecessor_id')
            ->unique();

        $successorIds = Relationship::where('project_id', $project->id)
            ->pluck('successor_id')
            ->unique();

        $errors = [];

        foreach ($milestones as $milestone) {
            $hasSuccessor = $predecessorIds->contains($milestone->id);
            $hasPredecessor = $successorIds->contains($milestone->id);

            if (!$hasSuccessor && !$hasPredecessor) {
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Critical,
                    message: "Milestone '{$milestone->name}' ({$milestone->activity_code}) is orphaned — no predecessors and no successors.",
                    context: [
                        'activity_instance_id' => $milestone->id,
                        'activity_code' => $milestone->activity_code,
                    ],
                );
            }
        }

        return $errors;
    }
}