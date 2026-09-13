<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ValidationSeverity;
use App\Models\DependencyRule;
use App\Models\OutputDefinition;
use App\Models\PackageInstance;
use App\Models\Project;

/**
 * Critical: Suppressed packages must not leave required outputs unresolved.
 * If a package is deselected or not applicable, check that no active package
 * has a mandatory dependency on its outputs.
 */
class SuppressedOutputRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        // Find all suppressed (deselected or not applicable) packages
        $suppressedInstances = PackageInstance::where('project_id', $project->id)
            ->where(fn ($q) => $q->where('selected', false)->orWhere('applicable', false))
            ->with('template.outputDefinitions')
            ->get();

        // Collect all output_keys from suppressed packages
        $suppressedOutputKeys = $suppressedInstances
            ->flatMap(fn ($inst) => $inst->template->outputDefinitions->pluck('output_key'))
            ->unique();

        if ($suppressedOutputKeys->isEmpty()) {
            return [];
        }

        // Find active packages that have mandatory dependencies on these output keys
        $activeInstances = PackageInstance::where('project_id', $project->id)
            ->where('selected', true)
            ->where('applicable', true)
            ->with('template.dependencyRules')
            ->get();

        $errors = [];

        foreach ($activeInstances as $instance) {
            foreach ($instance->template->dependencyRules as $rule) {
                if (!$rule->is_mandatory) {
                    continue;
                }

                if ($suppressedOutputKeys->contains($rule->required_output_key)) {
                    $errors[] = new ValidationResult(
                        ruleClass: self::class,
                        severity: ValidationSeverity::Critical,
                        message: "Suppressed package provides output '{$rule->required_output_key}' which is required by active package '{$instance->template->name}'.",
                        context: [
                            'required_output_key' => $rule->required_output_key,
                            'consumer_template_code' => $instance->template->code,
                            'consumer_package_instance_id' => $instance->id,
                        ],
                    );
                }
            }
        }

        return $errors;
    }
}