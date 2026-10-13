<?php

declare(strict_types=1);

use App\Enums\DeliveryModel;
use App\Models\ActivityInstance;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Models\Relationship;
use App\Services\Bess\DeliveryModelService;
use App\Services\Bess\DependencyResolverService;
use App\Services\Bess\GraphCompilerService;
use App\Services\Bess\PackageLibraryService;
use App\Services\Bess\ProjectSetupService;
use Database\Seeders\PackageLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The engines are database-backed, so both suites get the application and a
| fresh database per test.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Schedule helpers
|--------------------------------------------------------------------------
*/

function seedLibrary(): void
{
    test()->seed(PackageLibrarySeeder::class);
}

/**
 * Create a project through ProjectSetupService (WBS skeleton included).
 *
 * @param  array<string, mixed>  $config
 */
function makeProject(array $config = []): Project
{
    static $sequence = 0;
    $sequence++;

    return app(ProjectSetupService::class)->createProject(array_merge([
        'name' => "Test BESS {$sequence}",
        'code' => "TEST-{$sequence}",
        'start_date' => '2026-11-02',
        'delivery_model' => DeliveryModel::Epc,
    ], $config));
}

/** Instantiate packages and apply the project's delivery model. */
function preparePackages(Project $project): Project
{
    $library = app(PackageLibraryService::class);
    $library->instantiatePackages($project);
    app(DeliveryModelService::class)->apply($project, $library->getActivePackages($project));

    return $project;
}

/** Resolve outputs and compile the graph — the engine chain short of validation. */
function compileSchedule(Project $project): Project
{
    app(DependencyResolverService::class)->resolve($project);
    app(GraphCompilerService::class)->compile($project);

    return $project->refresh();
}

/**
 * A fully compiled project for the given configuration.
 *
 * @param  array<string, mixed>  $config
 */
function compiledProject(array $config = []): Project
{
    return compileSchedule(preparePackages(makeProject($config)));
}

function packageFor(Project $project, string $templateCode): PackageInstance
{
    return PackageInstance::where('project_id', $project->id)
        ->whereHas('template', fn ($q) => $q->where('code', $templateCode))
        ->with('template')
        ->firstOrFail();
}

function activityCoded(Project $project, string $code): ActivityInstance
{
    return ActivityInstance::where('project_id', $project->id)->where('activity_code', $code)->firstOrFail();
}

/** @return list<string> activity codes of the direct predecessors */
function predecessorCodes(ActivityInstance $activity): array
{
    return Relationship::where('successor_id', $activity->id)
        ->join('activity_instances', 'activity_instances.id', '=', 'relationships.predecessor_id')
        ->pluck('activity_instances.activity_code')
        ->all();
}

/** Kahn's algorithm: true when the relationship graph has no loops (P6 rejects loops). */
function scheduleIsAcyclic(Project $project): bool
{
    $ids = ActivityInstance::where('project_id', $project->id)->pluck('id')->all();
    $inDegree = array_fill_keys($ids, 0);
    $successors = [];

    foreach (Relationship::where('project_id', $project->id)->get(['predecessor_id', 'successor_id']) as $rel) {
        $inDegree[$rel->successor_id]++;
        $successors[$rel->predecessor_id][] = $rel->successor_id;
    }

    $queue = array_keys(array_filter($inDegree, fn (int $d) => $d === 0));
    $visited = 0;

    while ($queue !== []) {
        $id = array_pop($queue);
        $visited++;

        foreach ($successors[$id] ?? [] as $next) {
            if (--$inDegree[$next] === 0) {
                $queue[] = $next;
            }
        }
    }

    return $visited === count($ids);
}
