<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\ProjectStatus;
use App\Enums\ValidationSeverity;
use App\Models\Project;
use App\Models\ValidationError;
use App\Services\Bess\Validation\BatteryCountReconciliationRule;
use App\Services\Bess\Validation\EnergisationPrerequisiteRule;
use App\Services\Bess\Validation\InvalidPackageCombinationRule;
use App\Services\Bess\Validation\MissingCompletionRollupRule;
use App\Services\Bess\Validation\MissingOutputProviderRule;
use App\Services\Bess\Validation\OpenFinishRule;
use App\Services\Bess\Validation\OpenStartRule;
use App\Services\Bess\Validation\OrphanMilestoneRule;
use App\Services\Bess\Validation\SuppressedOutputRule;
use App\Services\Bess\Validation\ValidationResult;
use App\Services\Bess\Validation\ValidationRule;

class ValidationService
{
    /** @var ValidationRule[] */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new MissingOutputProviderRule(),
            new OpenStartRule(),
            new OpenFinishRule(),
            new OrphanMilestoneRule(),
            new InvalidPackageCombinationRule(),
            new EnergisationPrerequisiteRule(),
            new SuppressedOutputRule(),
            new MissingCompletionRollupRule(),
            new BatteryCountReconciliationRule(),
        ];
    }

    /**
     * Run all validation rules against a project.
     * Persists results to validation_errors table.
     *
     * @return ValidationResult[]
     */
    public function validate(Project $project): array
    {
        // Clear previous validation errors
        ValidationError::where('project_id', $project->id)->delete();

        $allResults = [];
        $now = now();

        foreach ($this->rules as $rule) {
            $results = $rule->validate($project);

            foreach ($results as $result) {
                ValidationError::create([
                    'project_id' => $project->id,
                    'rule_class' => class_basename($result->ruleClass),
                    'severity' => $result->severity,
                    'message' => $result->message,
                    'context' => $result->context,
                    'validated_at' => $now,
                ]);

                $allResults[] = $result;
            }
        }

        $project->update([
            'status' => ProjectStatus::Validated,
            'validated_at' => $now,
        ]);

        return $allResults;
    }

    /**
     * Check if the project has any critical validation errors (blocks export).
     */
    public function hasCriticalErrors(Project $project): bool
    {
        return ValidationError::where('project_id', $project->id)
            ->where('severity', ValidationSeverity::Critical)
            ->where('resolved', false)
            ->exists();
    }

    /**
     * Get validation summary counts.
     */
    public function summary(Project $project): array
    {
        $counts = ValidationError::where('project_id', $project->id)
            ->where('resolved', false)
            ->selectRaw('severity, count(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->all();

        return [
            'critical' => $counts[ValidationSeverity::Critical->value] ?? 0,
            'warning' => $counts[ValidationSeverity::Warning->value] ?? 0,
            'info' => $counts[ValidationSeverity::Info->value] ?? 0,
            'can_export' => !isset($counts[ValidationSeverity::Critical->value]) || $counts[ValidationSeverity::Critical->value] === 0,
        ];
    }
}