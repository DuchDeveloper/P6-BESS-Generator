<?php

declare(strict_types=1);

namespace App\Services\Bess\Strategies;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * EPC (Engineer, Procure, Construct): Full scope — all packages remain internal.
 * This is the default delivery model; no ownership overrides needed.
 */
class EpcDeliveryStrategy implements DeliveryModelStrategy
{
    public function apply(Project $project, Collection $packageInstances): Collection
    {
        // EPC: everything is internal by default — no overrides
        return $packageInstances;
    }

    public function description(): string
    {
        return 'Full EPC scope — all design, procurement, and construction packages are internal.';
    }
}