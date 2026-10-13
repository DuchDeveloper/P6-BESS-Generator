<?php

declare(strict_types=1);

use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Models\BlockConfiguration;
use App\Models\User;
use App\Services\Bess\TopologyService;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('explains the site layout fields in the create wizard', function (): void {
    Livewire::test(CreateProject::class)
        ->set('data.bess_architecture', 'separate_pcs_multi_per_sut')
        ->assertSee('How a site is divided')
        ->assertSee('one self-contained work area inside a zone')
        ->assertSee('Your site')
        ->assertSee('Batteries installed together as one job')
        ->assertSee('PCS (inverter) units installed together as one job')
        ->assertSee('Step-up transformers in each block');
});

it('talks about containers for an integrated-container site', function (): void {
    Livewire::test(CreateProject::class)
        ->set('data.bess_architecture', 'integrated_container')
        ->assertSee('Containers installed together as one job')
        ->assertDontSee('PCS (inverter) units installed together as one job');
});

it('explains the topology fields on the edit form', function (): void {
    $project = makeProject();

    $this->get("/admin/projects/{$project->id}/edit")
        ->assertOk()
        ->assertSee(['How a site is divided', 'A self-contained work area inside a zone', 'Total Battery Count']);
});

it('rebuilds the zones and blocks when the topology counts change', function (): void {
    $project = makeProject();
    app(TopologyService::class)->initializeFromDefaults($project);
    expect(BlockConfiguration::where('project_id', $project->id)->count())->toBe(1);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->fillForm(['zone_count' => 2, 'blocks_per_zone' => 3])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Zones and blocks updated');

    expect(BlockConfiguration::where('project_id', $project->id)->count())->toBe(6);
});

it('leaves the zones and blocks alone when nothing about the layout changed', function (): void {
    $project = makeProject();
    app(TopologyService::class)->initializeFromDefaults($project);
    $blockId = BlockConfiguration::where('project_id', $project->id)->value('id');

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->fillForm(['name' => 'Renamed project'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotNotified('Zones and blocks updated');

    expect(BlockConfiguration::where('project_id', $project->id)->value('id'))->toBe($blockId);
});
