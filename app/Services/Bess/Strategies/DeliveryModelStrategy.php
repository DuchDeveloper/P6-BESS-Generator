<?php

declare(strict_types=1);

namespace App\Services\Bess\Strategies;

use App\Models\Project;
use Illuminate\Support\Collection;

interface DeliveryModelStrategy
{
    /**
     * Apply ownership mode overrides to package instances based on the delivery model.
     *
     * @param  Project     $project
     * @param  Collection  $packageInstances  Collection of PackageInstance models
     * @return Collection  The modified package instances
     */
    public function apply(Project $project, Collection $packageInstances): Collection;

    /**
     * Get human-readable description of what this strategy does.
     */
    public function description(): string;
}