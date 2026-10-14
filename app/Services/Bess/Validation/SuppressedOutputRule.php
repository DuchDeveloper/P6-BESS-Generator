<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\OwnershipMode;
use App\Enums\ValidationSeverity;
use App\Models\DependencyRule;
use App\Models\OutputDefinition;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Services\Bess\DependencyResolverService;

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
            ->with(['template.dependencyRules', 'template.outputDefinitions'])
            ->get();

        // Outputs some active package does produce, for fallbacks.
        $providedOutputKeys = $activeInstances
            ->reject(fn (PackageInstance $inst) => $inst->ownership_mode === OwnershipMode::NotApplicable)
            ->flatMap(fn (PackageInstance $inst) => $inst->template->outputDefinitions->pluck('output_key'))
            ->mapWithKeys(fn (string $key) => [$key => true])
            ->all();

        $errors = [];

        foreach ($activeInstances as $instance) {
            foreach ($instance->template->dependencyRules as $rule) {
                if (!$rule->is_mandatory) {
                    continue;
                }

                if (! $suppressedOutputKeys->contains($rule->required_output_key)) {
                    continue;
                }

                // The rule names a fallback this project does produce: the
                // consumer waits on that instead. Worth knowing, not an error.
                $effective = DependencyResolverService::effectiveOutputKey($rule, $providedOutputKeys);
                if ($effective !== $rule->required_output_key) {
                    $errors[] = new ValidationResult(
                        ruleClass: self::class,
                        severity: ValidationSeverity::Info,
                        message: "'{$instance->template->name}' waits on '{$effective}' because '{$rule->required_output_key}' isn't produced in this project.",
                        context: [
                            'required_output_key' => $rule->required_output_key,
                            'fallback_output_key' => $effective,
                            'consumer_template_code' => $instance->template->code,
                            'consumer_package_instance_id' => $instance->id,
                        ],
                    );

                    continue;
                }

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

        return $errors;
    }
}