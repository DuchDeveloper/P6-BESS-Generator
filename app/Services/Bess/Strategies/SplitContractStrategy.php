<?php

declare(strict_types=1);

namespace App\Services\Bess\Strategies;

use App\Enums\Discipline;
use App\Enums\OwnershipMode;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Split Contract: Civil and structural design/construction is by one party,
 * electrical and commissioning by another. Typically:
 * - Civil discipline packages → external
 * - Electrical, SCADA, protection → internal
 * - Procurement → split based on discipline
 */
class SplitContractStrategy implements DeliveryModelStrategy
{
    public function apply(Project $project, Collection $packageInstances): Collection
    {
        $externalDisciplines = [
            Discipline::Civil,
        ];

        foreach ($packageInstances as $instance) {
            if (in_array($instance->template->discipline, $externalDisciplines, true)) {
                $instance->update([
                    'ownership_mode' => OwnershipMode::External,
                ]);
            }
        }

        return $packageInstances;
    }

    public function description(): string
    {
        return 'Split contract — civil packages external, electrical and commissioning internal.';
    }
}