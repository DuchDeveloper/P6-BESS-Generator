<?php

declare(strict_types=1);

use App\Enums\DeliveryModel;
use App\Enums\ProjectStatus;
use App\Enums\ValidationSeverity;
use App\Enums\WbsCategory;
use App\Filament\Resources\ProjectResource\Pages\ManageProject;
use App\Models\ActivityInstance;
use App\Models\User;
use App\Models\ValidationError;
use App\Models\WbsNode;
use App\Services\Bess\ValidationService;
use Livewire\Livewire;

beforeEach(function (): void {
    seedLibrary();
});

it('creates the six standard WBS branches for a new project', function (): void {
    $project = makeProject();

    $branches = WbsNode::where('project_id', $project->id)->where('level', 1)->orderBy('sort_order')->get();

    expect($branches->pluck('name')->all())->toBe(['Milestones', 'Project Management Plan', 'Design', 'Procurement', 'Construction', 'Commissioning'])
        ->and($branches->pluck('wbs_category')->all())->toBe(WbsCategory::cases());
});

it('keeps design packages out of construction and procurement out of design', function (): void {
    $project = compiledProject();

    $branchOf = function (ActivityInstance $activity): ?WbsCategory {
        $node = $activity->wbsNode;
        while ($node !== null && $node->level > 1) {
            $node = $node->parent;
        }

        return $node?->wbs_category;
    };

    $activities = ActivityInstance::where('project_id', $project->id)->with('packageInstance.template', 'wbsNode')->get()
        ->filter(fn ($a) => $a->packageInstance !== null);

    foreach ($activities as $activity) {
        expect($branchOf($activity))->toBe($activity->packageInstance->template->wbs_category);
    }
});

it('generates an export-ready schedule for a multi-zone site', function (DeliveryModel $model): void {
    $project = compiledProject(['delivery_model' => $model, 'zone_count' => 2, 'blocks_per_zone' => 2]);

    app(ValidationService::class)->validate($project);

    expect(ValidationError::where('project_id', $project->id)->where('severity', ValidationSeverity::Critical)->pluck('message')->all())->toBe([])
        ->and(scheduleIsAcyclic($project))->toBeTrue()
        ->and(ActivityInstance::where('project_id', $project->id)->where('activity_code', 'like', 'Z2-B2-%')->exists())->toBeTrue();
})->with([DeliveryModel::Epc, DeliveryModel::ConstructOnly]);

it('lets a planner run the engine chain from the Manage page and export', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
        ->callAction('instantiate')->assertNotified('Packages Instantiated')
        ->callAction('applyDeliveryModel')->assertNotified('Delivery Model Applied')
        ->callAction('resolve')->assertNotified('All Dependencies Resolved')
        ->callAction('compile')->assertNotified('Schedule Compiled')
        ->callAction('validate')->assertNotified('Validation Passed')
        // The roll-up notes are reviewable but never block anything.
        ->callAction('acknowledgeWarnings')->assertNotified('Warnings Acknowledged')
        ->assertActionHidden('acknowledgeWarnings')
        ->callAction('exportXer')
        ->assertFileDownloaded("{$project->code}.xer");

    expect($project->refresh()->status)->toBe(ProjectStatus::Exported);
});

it('builds the schedule in one step after a package change', function (): void {
    $this->actingAs(User::factory()->create());
    $project = compiledProject();
    packageFor($project, 'civil_fence_30')->update(['selected' => false]);

    Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
        ->callAction('buildSchedule')
        ->assertNotified('Schedule Built with Critical Errors');

    packageFor($project, 'civil_fence_30')->update(['selected' => true]);

    Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
        ->callAction('buildSchedule')
        ->assertNotified('Schedule Built');

    expect($project->refresh()->status)->toBe(ProjectStatus::Validated);
});

it('blocks export from the Manage page while critical errors remain', function (): void {
    $this->actingAs(User::factory()->create());
    $project = preparePackages(makeProject());
    packageFor($project, 'civil_fence_30')->update(['selected' => false]);
    compileSchedule($project);

    Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
        ->callAction('exportXer')
        ->assertNotified('Export Blocked')
        ->assertNoFileDownloaded();

    expect($project->refresh()->status)->not->toBe(ProjectStatus::Exported);
});

it('loads every project screen for a signed-in user', function (): void {
    $this->actingAs(User::factory()->create());
    $project = compiledProject();
    app(ValidationService::class)->validate($project);

    $this->get('/admin/projects')->assertOk()->assertSee($project->code);
    $this->get('/admin/projects/create')->assertOk();
    $this->get("/admin/projects/{$project->id}/edit")->assertOk();
    $this->get("/admin/projects/{$project->id}/manage")->assertOk()->assertSee(['Validation Issues', 'INFO', 'only rolls up to its phase closure']);
});
