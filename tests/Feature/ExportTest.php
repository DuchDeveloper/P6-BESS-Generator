<?php

declare(strict_types=1);

use App\Enums\P6TaskType;
use App\Enums\ProjectStatus;
use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\User;
use App\Models\ValidationError;
use App\Services\Bess\Export\JsonExportStrategy;
use App\Services\Bess\ExportService;
use App\Services\Bess\GraphCompilerService;

beforeEach(function (): void {
    seedLibrary();
    $this->exports = sys_get_temp_dir().'/bess-export-'.uniqid();
});

afterEach(function (): void {
    foreach (glob($this->exports.'/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($this->exports)) {
        rmdir($this->exports);
    }
});

/**
 * Parse an XER file into table name → list of rows keyed by field name.
 *
 * @return array<string, list<array<string, string>>>
 */
function parseXer(string $path): array
{
    $tables = [];
    $table = null;
    $fields = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $cells = explode("\t", $line);

        match ($cells[0]) {
            '%T' => [$table = $cells[1], $tables[$table] = []],
            '%F' => $fields = array_slice($cells, 1),
            '%R' => $tables[$table][] = array_combine($fields, array_pad(array_slice($cells, 1), count($fields), '')),
            default => null,
        };
    }

    return $tables;
}

it('exports a P6 XER with tasks, relationships and a start-on-or-after constraint', function (): void {
    $project = compiledProject();

    $path = app(ExportService::class)->export($project, 'xer', $this->exports);
    $xer = parseXer($path);

    $tasks = collect($xer['TASK']);
    $ntp = $tasks->firstWhere('task_code', 'MS-NTP-001');

    expect(basename($path))->toBe("{$project->code}.xer")
        ->and($tasks)->toHaveCount(ActivityInstance::where('project_id', $project->id)->count())
        ->and($tasks->pluck('task_code')->unique())->toHaveCount($tasks->count())
        ->and($xer['TASKPRED'])->toHaveCount($project->relationships()->count())
        ->and($ntp['cstr_type'])->toBe('CS_MSOA')
        ->and($ntp['cstr_date'])->toBe('2026-11-02 00:00')
        ->and($tasks->firstWhere('task_code', GraphCompilerService::PROJECT_COMPLETE_CODE)['task_type'])->toBe(P6TaskType::FinishMilestone->value)
        ->and($tasks->firstWhere('task_code', 'CIVIL-EQPAD-30-001')['task_type'])->toBe(P6TaskType::Task->value)
        ->and($tasks->pluck('cstr_type')->filter()->unique()->values()->all())->toBe(['CS_MSOA'])
        ->and($project->refresh()->status)->toBe(ProjectStatus::Exported);
});

it('validates the schedule itself before exporting', function (): void {
    $project = compiledProject();
    expect(ValidationError::where('project_id', $project->id)->exists())->toBeFalse();

    app(ExportService::class)->export($project, 'csv', $this->exports);

    expect($project->refresh()->validated_at)->not->toBeNull();
});

it('refuses to export a schedule with critical errors, even ones marked resolved', function (): void {
    $project = preparePackages(makeProject());
    packageFor($project, 'civil_fence_30')->update(['selected' => false]);
    compileSchedule($project);

    ValidationError::where('project_id', $project->id)->update(['resolved' => true]);

    expect(fn () => app(ExportService::class)->export($project, 'xer', $this->exports))
        ->toThrow(RuntimeException::class, 'critical validation error');

    expect(glob($this->exports.'/*') ?: [])->toBe([])
        ->and($project->refresh()->status)->not->toBe(ProjectStatus::Exported);
});

it('refuses to export a project that has not been compiled', function (): void {
    $project = preparePackages(makeProject());

    expect(fn () => app(ExportService::class)->export($project, 'xer', $this->exports))
        ->toThrow(RuntimeException::class, 'compile the schedule first');
});

it('names export files safely whatever the project code', function (): void {
    $project = compiledProject(['code' => 'BESS/../001 Rev A']);

    $path = app(ExportService::class)->export($project, 'csv', $this->exports);

    expect(basename($path))->toBe('BESS-..-001-Rev-A.csv')
        ->and(dirname($path))->toBe($this->exports);
});

it('exports a CSV with predecessor references', function (): void {
    $project = compiledProject();

    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), file(app(ExportService::class)->export($project, 'csv', $this->exports), FILE_IGNORE_NEW_LINES));
    $header = array_shift($rows);
    $byCode = collect($rows)->keyBy(fn ($row) => $row[0]);

    expect($header[0])->toBe('Activity Code')
        ->and($byCode)->toHaveCount(ActivityInstance::where('project_id', $project->id)->count())
        ->and($byCode['CIVIL-EQPAD-30-002'][7])->toBe('CIVIL-EQPAD-30-001FS');
});

it('exports the graph as JSON', function (): void {
    $project = compiledProject();

    $payload = json_decode(file_get_contents(app(ExportService::class)->export($project, 'json', $this->exports)), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['format'])->toBe(JsonExportStrategy::FORMAT_VERSION)
        ->and($payload['project']['code'])->toBe($project->code)
        ->and($payload['activities'])->toHaveCount(ActivityInstance::where('project_id', $project->id)->count())
        ->and($payload['relationships'])->toHaveCount($project->relationships()->count())
        ->and(collect($payload['relationships'])->firstWhere('successor', 'CIVIL-EQPAD-30-002'))
        ->toMatchArray(['predecessor' => 'CIVIL-EQPAD-30-001', 'type' => 'FS'])
        ->and(collect($payload['outputs'])->firstWhere('output_key', 'bod_concept_ga_approved')['provider_package'])->toBe('bod_concept_ga');
});

it('lists every export format and rejects unknown ones', function (): void {
    $service = app(ExportService::class);

    expect(collect($service->availableFormats())->pluck('value')->all())->toBe(['xer', 'csv', 'json'])
        ->and(fn () => $service->resolveStrategy('pdf'))->toThrow(RuntimeException::class);
});

it('keeps project reports behind the login', function (): void {
    $project = compiledProject();

    $this->get(route('bess.gantt.show', $project))->assertRedirect(route('filament.admin.auth.login'));
    $this->get(route('bess.bos.latest', $project))->assertRedirect(route('filament.admin.auth.login'));

    $this->actingAs(User::factory()->create())
        ->get(route('bess.gantt.show', $project))
        ->assertOk()
        ->assertSee($project->name);
});

it('404s a report for a project that does not exist', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/projects/999999/summary-gantt')
        ->assertNotFound();

    expect(Project::find(999999))->toBeNull();
});
