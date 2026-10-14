<?php

declare(strict_types=1);

use App\Enums\OwnershipMode;
use App\Filament\Resources\ProjectResource\Pages\ManageProject;
use App\Models\User;
use App\Services\Bess\PackageLibraryService;
use App\Services\Bess\ValidationService;
use Livewire\Livewire;

beforeEach(function (): void {
    seedLibrary();
    $this->actingAs(User::factory()->create());
    $this->project = preparePackages(makeProject());
});

it('includes a package in another one from the Manage page', function (): void {
    $fence = packageFor($this->project, 'civil_fence_30');
    $road = packageFor($this->project, 'civil_road_30');

    Livewire::test(ManageProject::class, ['record' => $this->project->getRouteKey()])
        ->callTableAction('editOwnership', $fence, data: [
            'ownership_mode' => OwnershipMode::IncludedElsewhere->value,
            'included_in_package_id' => $road->id,
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Ownership updated')
        ->searchTable('civil_fence_30')
        ->assertSee('In: Road and Hardstand Design 30%');

    expect($fence->refresh()->ownership_mode)->toBe(OwnershipMode::IncludedElsewhere)
        ->and($fence->included_in_package_id)->toBe($road->id);

    // The schedule still builds cleanly.
    compileSchedule($this->project);
    app(ValidationService::class)->validate($this->project);
    expect(app(ValidationService::class)->hasCriticalErrors($this->project))->toBeFalse();
});

it('asks which package includes it', function (): void {
    Livewire::test(ManageProject::class, ['record' => $this->project->getRouteKey()])
        ->callTableAction('editOwnership', packageFor($this->project, 'civil_fence_30'), data: [
            'ownership_mode' => OwnershipMode::IncludedElsewhere->value,
            'included_in_package_id' => null,
        ])
        ->assertHasTableActionErrors(['included_in_package_id' => 'required']);
});

it('only offers active packages that do their own work as containers', function (): void {
    $fence = packageFor($this->project, 'civil_fence_30');
    $library = app(PackageLibraryService::class);
    packageFor($this->project, 'civil_drain_30')->update(['selected' => false]);
    packageFor($this->project, 'civil_eqpad_30')->update(['ownership_mode' => OwnershipMode::NotApplicable]);

    $options = $library->containerOptions($fence);

    expect($options)->toHaveKey(packageFor($this->project, 'civil_road_30')->id)
        ->not->toHaveKey($fence->id)
        ->not->toHaveKey(packageFor($this->project, 'civil_drain_30')->id)
        ->not->toHaveKey(packageFor($this->project, 'civil_eqpad_30')->id)
        ->and(fn () => $library->setOwnershipMode($fence, OwnershipMode::IncludedElsewhere, $fence->id))
        ->toThrow(InvalidArgumentException::class);
});

it('changes the ownership of several packages at once', function (): void {
    $packages = collect(['civil_fence_30', 'civil_fence_60', 'civil_fence_90'])->map(fn ($code) => packageFor($this->project, $code));

    Livewire::test(ManageProject::class, ['record' => $this->project->getRouteKey()])
        ->callTableBulkAction('setOwnership', $packages, data: ['ownership_mode' => OwnershipMode::External->value])
        ->assertNotified('Ownership updated');

    expect($packages->map(fn ($p) => $p->refresh()->ownership_mode)->unique()->all())->toBe([OwnershipMode::External]);
});
