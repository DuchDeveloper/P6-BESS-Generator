<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\DeliveryModel;
use App\Models\Project;
use App\Services\Bess\Strategies\BopFreeIssuedStrategy;
use App\Services\Bess\Strategies\ConstructOnlyStrategy;
use App\Services\Bess\Strategies\DeliveryModelStrategy;
use App\Services\Bess\Strategies\EpcDeliveryStrategy;
use App\Services\Bess\Strategies\OwnerProvidedDesignStrategy;
use App\Services\Bess\Strategies\SplitContractStrategy;
use Illuminate\Support\Collection;

class DeliveryModelService
{
    /**
     * Apply the project's delivery model strategy to its package instances.
     */
    public function apply(Project $project, Collection $packageInstances): Collection
    {
        $strategy = $this->resolveStrategy($project->delivery_model);

        return $strategy->apply($project, $packageInstances);
    }

    /**
     * Get the strategy instance for a delivery model.
     */
    public function resolveStrategy(DeliveryModel $model): DeliveryModelStrategy
    {
        return match ($model) {
            DeliveryModel::Epc => new EpcDeliveryStrategy(),
            DeliveryModel::BopFreeIssued => new BopFreeIssuedStrategy(),
            DeliveryModel::OwnerProvidedDesign => new OwnerProvidedDesignStrategy(),
            DeliveryModel::ConstructOnly => new ConstructOnlyStrategy(),
            DeliveryModel::SplitContract => new SplitContractStrategy(),
        };
    }

    /**
     * Get available delivery models with descriptions.
     */
    public function availableModels(): array
    {
        return collect(DeliveryModel::cases())->map(fn (DeliveryModel $model) => [
            'value' => $model->value,
            'label' => $model->label(),
            'description' => $this->resolveStrategy($model)->description(),
        ])->all();
    }
}