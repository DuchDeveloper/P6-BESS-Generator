<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\RelationshipOrigin;
use App\Enums\ValidationSeverity;
use App\Models\ActivityInstance;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Models\Relationship;

/**
 * Warning: Package completion milestones should drive a downstream package or roll up
 * to a phase closure. Flags milestones that don't connect to anything, and notes
 * (Info) those that only roll up so a planner can confirm that's intended.
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

        // predecessor id → origins of its outgoing links
        $outgoing = Relationship::where('project_id', $project->id)
            ->get(['predecessor_id', 'origin'])
            ->groupBy('predecessor_id');

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

            $links = $outgoing->get($completionMilestone->id);

            // Drives nothing at all
            if ($links === null) {
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Warning,
                    message: "Package completion milestone '{$completionMilestone->name}' does not drive any downstream package or roll-up.",
                    context: [
                        'activity_instance_id' => $completionMilestone->id,
                        'package_template_code' => $instance->template->code,
                    ],
                );

                continue;
            }

            // Only rolls up to its phase closure — allowed, but worth a
            // planner's look in case it should gate real downstream work.
            if ($links->every(fn (Relationship $r) => $r->origin === RelationshipOrigin::Rollup)) {
                $errors[] = new ValidationResult(
                    ruleClass: self::class,
                    severity: ValidationSeverity::Info,
                    message: "Package completion milestone '{$completionMilestone->name}' only rolls up to its phase closure. Check whether it should drive a downstream package.",
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