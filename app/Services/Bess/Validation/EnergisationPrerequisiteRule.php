<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ValidationSeverity;
use App\Models\EnergisationMilestone;
use App\Models\Project;
use App\Models\ProviderResolution;

/**
 * Critical: Every energisation milestone must have all its prerequisite groups resolved.
 */
class EnergisationPrerequisiteRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        $milestones = EnergisationMilestone::where('project_id', $project->id)
            ->where('selected', true)
            ->where('applicable', true)
            ->with('prerequisites')
            ->get();

        $errors = [];

        foreach ($milestones as $milestone) {
            foreach ($milestone->prerequisites as $prerequisite) {
                if ($prerequisite->is_resolved) {
                    continue;
                }

                // Check if the required output is resolved in provider_resolutions
                $resolution = ProviderResolution::where('project_id', $project->id)
                    ->where('output_key', $prerequisite->required_output_key)
                    ->first();

                if (!$resolution || $resolution->resolution_type->value === 'unresolved') {
                    $errors[] = new ValidationResult(
                        ruleClass: self::class,
                        severity: ValidationSeverity::Critical,
                        message: "Energisation milestone '{$milestone->name}' is missing prerequisite '{$prerequisite->description}' (group: {$prerequisite->group_name->value}).",
                        context: [
                            'energisation_milestone_id' => $milestone->id,
                            'prerequisite_group' => $prerequisite->group_name->value,
                            'required_output_key' => $prerequisite->required_output_key,
                        ],
                    );
                }
            }
        }

        return $errors;
    }
}