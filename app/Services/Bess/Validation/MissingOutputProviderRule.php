<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ResolutionType;
use App\Enums\ValidationSeverity;
use App\Models\Project;
use App\Models\ProviderResolution;

/**
 * Critical: Every required output_key must have a resolved provider.
 */
class MissingOutputProviderRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        $unresolved = ProviderResolution::where('project_id', $project->id)
            ->where('resolution_type', ResolutionType::Unresolved)
            ->get();

        return $unresolved->map(fn (ProviderResolution $r) => new ValidationResult(
            ruleClass: self::class,
            severity: ValidationSeverity::Critical,
            message: "Output key '{$r->output_key}' has no resolved provider.",
            context: ['output_key' => $r->output_key],
        ))->all();
    }
}