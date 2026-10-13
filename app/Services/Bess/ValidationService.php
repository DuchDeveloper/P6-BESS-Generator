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
     * Critical errors can't be acknowledged away — only fixed (CLAUDE.md §15).
     */
    public function hasCriticalErrors(Project $project): bool
    {
        return $this->criticalCount($project) > 0;
    }

    public function criticalCount(Project $project): int
    {
        return ValidationError::where('project_id', $project->id)
            ->where('severity', ValidationSeverity::Critical)
            ->count();
    }

    /**
     * Mark every outstanding warning and info result as reviewed. Critical
     * errors are left alone: they block export until the schedule is fixed.
     *
     * @return int  The number of results acknowledged
     */
    public function acknowledgeWarnings(Project $project, string $note = 'Reviewed by user'): int
    {
        return ValidationError::where('project_id', $project->id)
            ->where('severity', '!=', ValidationSeverity::Critical)
            ->where('resolved', false)
            ->update([
                'resolved' => true,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);
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

        $critical = $this->criticalCount($project);

        return [
            'critical' => $critical,
            'warning' => (int) ($counts[ValidationSeverity::Warning->value] ?? 0),
            'info' => (int) ($counts[ValidationSeverity::Info->value] ?? 0),
            'can_export' => $critical === 0,
        ];
    }
}
