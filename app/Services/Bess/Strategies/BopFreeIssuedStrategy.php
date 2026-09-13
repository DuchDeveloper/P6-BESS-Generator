<?php

declare(strict_types=1);

namespace App\Services\Bess\Strategies;

use App\Enums\OwnershipMode;
use App\Enums\PackageType;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * BOP Free-Issued: BESS equipment is free-issued by the owner.
 * Suppress BESS procurement, keep all design and construction internal.
 */
class BopFreeIssuedStrategy implements DeliveryModelStrategy
{
    public function apply(Project $project, Collection $packageInstances): Collection
    {
        foreach ($packageInstances as $instance) {
            $templateCode = $instance->template->code;

            // Suppress BESS procurement — equipment is free-issued
            if ($templateCode === 'proc_bess') {
                $instance->update([
                    'ownership_mode' => OwnershipMode::NotApplicable,
                    'selected' => false,
                    'exclusion_reason' => 'BESS equipment free-issued by owner',
                ]);
            }
        }

        return $packageInstances;
    }

    public function description(): string
    {
        return 'BOP scope with BESS equipment free-issued by the owner. BESS procurement suppressed.';
    }
}