<?php

declare(strict_types=1);

namespace App\Services\Bess\Validation;

use App\Enums\ValidationSeverity;

readonly class ValidationResult
{
    public function __construct(
        public string $ruleClass,
        public ValidationSeverity $severity,
        public string $message,
        public array $context = [],
    ) {}
}