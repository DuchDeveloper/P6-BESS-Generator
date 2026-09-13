<?php

declare(strict_types=1);

namespace App\Services\Bess\Strategies;

use App\Enums\OwnershipMode;
use App\Enums\PackageType;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Construct Only: Design and procurement are external (owner-provided).
 * Only construction and commissioning packages are internal.
 */
class ConstructOnlyStrategy implements DeliveryModelStrategy
{
    public function apply(Project $project, Collection $packageInstances): Collection
    {
        foreach ($packageInstances as $instance) {
            $type = $instance->template->type;

            if ($type === PackageType::Design || $type === PackageType::Procurement) {
                $instance->update([
                    'ownership_mode' => OwnershipMode::External,
                ]);
            }
        }

        return $packageInstances;
    }

    public function description(): string
    {
        return 'Construction only — design and procurement external. Construction and commissioning internal.';
    }
}