<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ValidationSeverity;
use App\Models\ActivityInstance;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Models\Relationship;

/**
 * Warning: Package completion milestones should drive a downstream package or roll up
 * to a phase closure. Flags milestones that don't connect to anything.
 */
class MissingCompletionRollupRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        // Find all completion milestones (last activity in each package)
        $activePackages = PackageInstance::where('project_id', $project->id)
            ->where('selected', true)
            ->where('applicable', true)
            ->with('template')
            ->get();

        $predecessorIds = Relationship::where('project_id', $project->id)
            ->pluck('predecessor_id')
            ->unique();

        $errors = [];

        foreach ($activePackages as $instance) {
            // Find the last milestone activity for this package
            $completionMilestone = ActivityInstance::where('package_instance_id', $instance->id)
                ->where('is_milestone', true)
                ->orderBy('sequence', 'desc')
                ->first();

            if ($completionMilestone === null) {
                continue;
            }

            // Check if it drives a downstream activity
            if (!$predecessorIds->contains($completionMilestone->id)) {
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Warning,
                    message: "Package completion milestone '{$completionMilestone->name}' does not drive any downstream package or roll-up.",
                    context: [
                        'activity_instance_id' => $completionMilestone->id,
                        'package_template_code' => $instance->template->code,
                    ],
                );
            }
        }

        return $errors;
    }
}