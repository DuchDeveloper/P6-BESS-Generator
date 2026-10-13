<?php

declare(strict_types=1);

use App\Enums\DeliveryModel;
use App\Enums\Discipline;
use App\Enums\OwnershipMode;
use App\Enums\PackageType;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Services\Bess\DeliveryModelService;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    seedLibrary();
});

/** @return array<string, OwnershipMode> template code → ownership mode, for selected packages */
function ownershipByCode(Project $project): array
{
    return PackageInstance::where('project_id', $project->id)
        ->with('template')
        ->get()
        ->mapWithKeys(fn (PackageInstance $p) => [$p->template->code => $p->ownership_mode])
        ->all();
}

function packagesOfType(Project $project, PackageType $type): Collection
{
    return PackageInstance::where('project_id', $project->id)
        ->where('selected', true)
        ->whereHas('template', fn ($q) => $q->where('type', $type))
        ->get();
}

it('keeps every package internal under EPC', function (): void {
    $project = preparePackages(makeProject(['delivery_model' => DeliveryModel::Epc]));

    expect(collect(ownershipByCode($project))->unique()->values()->all())->toBe([OwnershipMode::Internal]);
});

it('makes design external and leaves the rest internal for owner-provided design', function (): void {
    $project = preparePackages(makeProject(['delivery_model' => DeliveryModel::OwnerProvidedDesign]));

    expect(packagesOfType($project, PackageType::Design)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::External])
        ->and(packagesOfType($project, PackageType::Procurement)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::Internal])
        ->and(packagesOfType($project, PackageType::Construction)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::Internal]);
});

it('makes design and procurement external for construct-only', function (): void {
    $project = preparePackages(makeProject(['delivery_model' => DeliveryModel::ConstructOnly]));

    expect(packagesOfType($project, PackageType::Design)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::External])
        ->and(packagesOfType($project, PackageType::Procurement)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::External])
        ->and(packagesOfType($project, PackageType::Commissioning)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::Internal]);
});

it('makes civil packages external under a split contract', function (): void {
    $project = preparePackages(makeProject(['delivery_model' => DeliveryModel::SplitContract]));

    $instances = PackageInstance::where('project_id', $project->id)->where('selected', true)->with('template')->get();

    expect($instances->filter(fn ($p) => $p->template->discipline === Discipline::Civil)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::External])
        ->and($instances->reject(fn ($p) => $p->template->discipline === Discipline::Civil)->pluck('ownership_mode')->unique()->all())->toBe([OwnershipMode::Internal]);
});

it('suppresses BESS procurement and its PO award when the BESS is free-issued', function (): void {
    $project = preparePackages(makeProject(['delivery_model' => DeliveryModel::BopFreeIssued]));

    foreach (['proc_bess', 'ms_bess_po_award'] as $code) {
        $package = packageFor($project, $code);

        expect($package->selected)->toBeFalse()
            ->and($package->ownership_mode)->toBe(OwnershipMode::NotApplicable)
            ->and($package->exclusion_reason)->toBe('BESS equipment free-issued by owner');
    }

    expect(packageFor($project, 'proc_transformer')->selected)->toBeTrue();
});

it('describes every delivery model', function (): void {
    $models = app(DeliveryModelService::class)->availableModels();

    expect(collect($models)->pluck('value')->all())->toBe(collect(DeliveryModel::cases())->pluck('value')->all())
        ->and(collect($models)->every(fn (array $m) => $m['description'] !== ''))->toBeTrue();
});
