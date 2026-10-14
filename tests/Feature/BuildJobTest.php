<?php

declare(strict_types=1);

use App\Enums\BuildStatus;
use App\Filament\Resources\ProjectResource\Pages\ManageProject;
use App\Jobs\BuildProjectSchedule;
use App\Models\User;
use App\Services\Bess\ExportService;
use App\Services\Bess\GraphCompilerService;
use App\Services\Bess\ProjectBusyException;
use App\Services\Bess\ProjectLock;
use App\Services\Bess\ScheduleBuilder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    seedLibrary();
    $this->actingAs(User::factory()->create());
});

it('records each build and its outcome on the project', function (): void {
    $project = preparePackages(makeProject());

    app(ScheduleBuilder::class)->queue($project);
    $project->refresh();

    expect($project->build_status)->toBe(BuildStatus::Succeeded)
        ->and($project->isBuilding())->toBeFalse()
        ->and($project->build_message)->toBe('Ready to export.')
        ->and($project->build_summary)->toMatchArray(['critical' => 0, 'can_export' => true])
        ->and($project->build_summary['activities'])->toBeGreaterThan(500)
        ->and($project->build_started_at)->not->toBeNull()
        ->and($project->build_finished_at)->not->toBeNull();
});

it('queues the build and shows its progress while a worker runs it', function (): void {
    Queue::fake();
    $project = preparePackages(makeProject());

    Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
        ->callAction('buildSchedule')
        ->assertNotified('Build Started')
        ->assertActionHidden('buildSchedule')
        ->assertSee('Building schedule')
        ->assertSee('Waiting to start');

    Queue::assertPushed(BuildProjectSchedule::class, fn (BuildProjectSchedule $job) => $job->projectId === $project->id);
    expect($project->refresh()->isBuilding())->toBeTrue();
});

it('tells the planner when a background build finishes', function (): void {
    Queue::fake();
    $project = preparePackages(makeProject());

    $page = Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
        ->callAction('buildSchedule');

    // The worker picks the job up.
    (new BuildProjectSchedule($project->id))->handle(app(ScheduleBuilder::class));

    $page->call('checkBuild')
        ->assertNotified('Schedule Built')
        ->assertActionVisible('buildSchedule')
        ->assertSee('Ready to export.');
});

it('records a failed build without breaking the page', function (): void {
    $this->mock(GraphCompilerService::class)->shouldReceive('compile')->andThrow(new RuntimeException('Template data is broken'));
    $project = preparePackages(makeProject());

    Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
        ->callAction('buildSchedule')
        ->assertNotified('Build Failed');

    expect($project->refresh()->build_status)->toBe(BuildStatus::Failed)
        ->and($project->build_message)->toBe('Template data is broken');
});

it('stops treating a build as running once it has gone quiet for too long', function (): void {
    $project = makeProject();
    $project->forceFill(['build_status' => BuildStatus::Running, 'build_started_at' => now()->subMinutes(20)])->save();

    expect($project->isBuilding())->toBeFalse();

    $project->forceFill(['build_started_at' => now()->subMinute()])->save();
    expect($project->isBuilding())->toBeTrue();
});

it('marks the project failed when the worker gives up on the job', function (): void {
    $project = makeProject();
    $project->forceFill(['build_status' => BuildStatus::Running, 'build_started_at' => now()])->save();

    (new BuildProjectSchedule($project->id))->failed(new RuntimeException('Timed out'));

    expect($project->refresh()->build_status)->toBe(BuildStatus::Failed)
        ->and($project->build_message)->toContain('Timed out');
});

it('lets only one build or export run per project at a time', function (): void {
    $project = compiledProject();
    $other = compiledProject();

    app(ProjectLock::class)->run($project, function () use ($project, $other): void {
        expect(fn () => app(ExportService::class)->export($project, 'csv', sys_get_temp_dir()))->toThrow(ProjectBusyException::class)
            ->and(fn () => app(ScheduleBuilder::class)->build($project))->toThrow(ProjectBusyException::class);

        // Other projects aren't held up.
        $path = app(ExportService::class)->export($other, 'csv');
        expect($path)->toBeFile();
        app(ExportService::class)->cleanUp($path);
    });
});

it('fails a queued build that finds the project busy', function (): void {
    $project = preparePackages(makeProject());

    app(ProjectLock::class)->run($project, fn () => app(ScheduleBuilder::class)->queue($project));

    expect($project->refresh()->build_status)->toBe(BuildStatus::Failed)
        ->and($project->build_message)->toContain('Another build or export is running');
});

it('tells the planner the project is busy instead of running a step', function (): void {
    $project = compiledProject();

    app(ProjectLock::class)->run($project, function () use ($project): void {
        Livewire::test(ManageProject::class, ['record' => $project->getRouteKey()])
            ->callAction('compile')
            ->assertNotified('Project Busy');
    });
});

it('gives every export its own file and removes it afterwards', function (): void {
    $project = compiledProject();
    $exports = app(ExportService::class);

    $first = $exports->export($project, 'xer');
    $second = $exports->export($project, 'xer');

    expect($first)->not->toBe($second)
        ->and(basename($first))->toBe("{$project->code}.xer")
        ->and(dirname($first))->toStartWith(storage_path('app/exports'));

    $exports->cleanUp($first);
    $exports->cleanUp($second);

    expect(file_exists($first))->toBeFalse()
        ->and(is_dir(dirname($first)))->toBeFalse();
});
