<?php

declare(strict_types=1);

use App\Enums\OwnershipMode;
use App\Enums\ResolutionType;
use App\Models\PackageInstance;
use App\Models\ProviderResolution;
use App\Services\Bess\DependencyResolverService;
use App\Services\Bess\PackageLibraryService;

beforeEach(function (): void {
    seedLibrary();
    $this->project = preparePackages(makeProject());
    $this->resolver = app(DependencyResolverService::class);
});

it('resolves every required output of a default EPC project', function (): void {
    expect($this->resolver->resolve($this->project))->toBe([])
        ->and(ProviderResolution::where('project_id', $this->project->id)->where('resolution_type', ResolutionType::Unresolved)->count())->toBe(0)
        ->and(ProviderResolution::where('project_id', $this->project->id)->where('output_key', 'bod_concept_ga_approved')->value('provider_package_instance_id'))
        ->toBe(packageFor($this->project, 'bod_concept_ga')->id);
});

it('gives each design review every selected discipline approval at its maturity level', function (): void {
    $active = app(PackageLibraryService::class)->getActivePackages($this->project);
    $review30 = packageFor($this->project, 'dm_30_review');

    $keys = $this->resolver->gatherDynamicPredecessors($active)[$review30->id];

    expect($keys)->toContain('civil_30_eqpad_approved', 'civil_30_fence_approved', 'elec_p_30_feeder_approved')
        ->not->toContain('civil_60_eqpad_approved', 'dm_60_review_approved');
});

it('drops a deselected discipline package from the review predecessors', function (): void {
    packageFor($this->project, 'civil_fence_30')->update(['selected' => false]);
    $active = app(PackageLibraryService::class)->getActivePackages($this->project);

    $keys = $this->resolver->gatherDynamicPredecessors($active)[packageFor($this->project, 'dm_30_review')->id];

    expect($keys)->not->toContain('civil_30_fence_approved')
        ->toContain('civil_30_road_approved');
});

it('resolves an included-elsewhere output through the containing package', function (): void {
    $road = packageFor($this->project, 'civil_road_30');
    packageFor($this->project, 'civil_fence_30')->update([
        'ownership_mode' => OwnershipMode::IncludedElsewhere,
        'included_in_package_id' => $road->id,
    ]);

    expect($this->resolver->resolve($this->project))->toBe([]);

    $resolution = ProviderResolution::where('project_id', $this->project->id)->where('output_key', 'civil_30_fence_approved')->first();
    expect($resolution->provider_package_instance_id)->toBe($road->id)
        ->and($resolution->resolution_type)->toBe(ResolutionType::Internal);
});

it('treats the outputs of a not-applicable package as not required', function (): void {
    packageFor($this->project, 'civil_fence_30')->update(['ownership_mode' => OwnershipMode::NotApplicable]);

    expect($this->resolver->resolve($this->project))->toBe([])
        ->and(ProviderResolution::where('project_id', $this->project->id)->where('output_key', 'civil_30_fence_approved')->exists())->toBeFalse();
});

it('records an external provider as an external resolution', function (): void {
    packageFor($this->project, 'bod_concept_ga')->update(['ownership_mode' => OwnershipMode::External]);

    $this->resolver->resolve($this->project);

    expect(ProviderResolution::where('project_id', $this->project->id)->where('output_key', 'bod_concept_ga_approved')->value('resolution_type'))
        ->toBe(ResolutionType::External);
});

it('reports a mandatory output whose provider was deselected', function (): void {
    packageFor($this->project, 'civil_fence_30')->update(['selected' => false]);

    $unresolved = collect($this->resolver->resolve($this->project));

    expect($unresolved->pluck('output_key'))->toContain('civil_30_fence_approved')
        ->and($unresolved->firstWhere('output_key', 'civil_30_fence_approved')['consumer_template_code'])->toBe('civil_fence_60');
});

it('starts from a clean slate on every run', function (): void {
    $this->resolver->resolve($this->project);
    $first = ProviderResolution::where('project_id', $this->project->id)->count();

    $this->resolver->resolve($this->project);

    expect(ProviderResolution::where('project_id', $this->project->id)->count())->toBe($first)
        ->and(PackageInstance::where('project_id', $this->project->id)->count())->toBeGreaterThan(0);
});
