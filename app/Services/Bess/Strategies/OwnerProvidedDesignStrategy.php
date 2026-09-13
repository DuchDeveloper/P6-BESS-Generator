<?php

declare(strict_types=1);

namespace App\Services\Bess\Strategies;

use App\Enums\OwnershipMode;
use App\Enums\PackageType;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Owner-Provided Design: All design packages are external (owner provides).
 * Procurement and construction remain internal.
 * Boundary receipt milestones generated for each external design package.
 */
class OwnerProvidedDesignStrategy implements DeliveryModelStrategy
{
    public function apply(Project $project, Collection $packageInstances): Collection
    {
        foreach ($packageInstances as $instance) {
            if ($instance->template->type === PackageType::Design) {
                $instance->update([
                    'ownership_mode' => OwnershipMode::External,
                ]);
            }
        }

        return $packageInstances;
    }

    public function description(): string
    {
        return 'Design provided by owner — all design packages external with boundary milestones. Procurement and construction internal.';
    }
}