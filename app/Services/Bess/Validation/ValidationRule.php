<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Models\Project;

interface ValidationRule
{
    /**
     * Validate a project and return an array of validation results.
     *
     * @return ValidationResult[]
     */
    public function validate(Project $project): array;
}