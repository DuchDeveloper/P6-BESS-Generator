<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\OwnershipMode;
use App\Enums\ValidationSeverity;
use App\Models\PackageInstance;
use App\Models\Project;

/**
 * Critical: No package may have conflicting ownership states.
 * E.g., a package cannot be both external and included_elsewhere.
 * Also validates that included_elsewhere packages reference a valid containing package.
 */
class InvalidPackageCombinationRule implements ValidationRule
{
    public function validate(Project $project): array
    {
        $instances = PackageInstance::where('project_id', $project->id)
            ->where('selected', true)
            ->where('applicable', true)
            ->with('template')
            ->get();

        $errors = [];

        foreach ($instances as $instance) {
            // Included_elsewhere must reference a valid containing package
            if ($instance->ownership_mode === OwnershipMode::IncludedElsewhere) {
                if ($instance->included_in_package_id === null) {
                    $errors[] = new ValidationResult(
                        ruleClass: self::class,
                        severity: ValidationSeverity::Critical,
                        message: "Package '{$instance->template->name}' is marked as 'included elsewhere' but no containing package is specified.",
                        context: [
                            'package_instance_id' => $instance->id,
                            'template_code' => $instance->template->code,
                        ],
                    );
                    continue;
                }

                $containingPackage = PackageInstance::find($instance->included_in_package_id);
                if (!$containingPackage || !$containingPackage->selected || !$containingPackage->applicable) {
                    $errors[] = new ValidationResult(
                        ruleClass: self::class,
                        severity: ValidationSeverity::Critical,
                        message: "Package '{$instance->template->name}' references containing package ID {$instance->included_in_package_id} which is not active.",
                        context: [
                            'package_instance_id' => $instance->id,
                            'included_in_package_id' => $instance->included_in_package_id,
                        ],
                    );
                }
            }
        }

        return $errors;
    }
}