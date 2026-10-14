<?php

declare(strict_types=1);

use App\Enums\DeliveryModel;
use App\Enums\OwnershipMode;
use App\Enums\P6TaskType;
use App\Enums\RelationshipOrigin;
use App\Enums\RelationshipType;
use App\Enums\ValidationSeverity;
use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\Relationship;
use App\Models\ValidationError;
use App\Services\Bess\Validation\InvalidPackageCombinationRule;
use App\Services\Bess\Validation\MissingCompletionRollupRule;
use App\Services\Bess\Validation\OpenFinishRule;
use App\Services\Bess\Validation\OpenStartRule;
use App\Services\Bess\Validation\OrphanMilestoneRule;
use App\Services\Bess\Validation\SuppressedOutputRule;
use App\Services\Bess\Validation\ValidationResult;
use App\Services\Bess\ValidationService;

beforeEach(function (): void {
    seedLibrary();
    $this->validator = app(ValidationService::class);
});

/**
 * @param  list<ValidationResult>  $results
 * @return list<string>
 */
function rulesHit(array $results, ValidationSeverity $severity): array
{
    return collect($results)
        ->where('severity', $severity)
        ->map(fn (ValidationResult $r) => class_basename($r->ruleClass))
        ->unique()
        ->values()
        ->all();
}

function strayActivity(Project $project, string $code, bool $milestone = false): ActivityInstance
{
    return ActivityInstance::create([
        'project_id' => $project->id,
        'package_instance_id' => null,
        'wbs_node_id' => $project->wbsNodes()->where('level', 1)->value('id'),
        'activity_code' => $code,
        'name' => "Stray {$code}",
        'duration_days' => $milestone ? 0 : 5,
        'is_milestone' => $milestone,
        'sequence' => 1,
        'p6_task_type' => $milestone ? P6TaskType::FinishMilestone : P6TaskType::Task,
    ]);
}

it('passes a freshly generated schedule for every delivery model with no critical errors or warnings', function (DeliveryModel $model): void {
    $project = compiledProject(['delivery_model' => $model]);

    $results = $this->validator->validate($project);

    expect(rulesHit($results, ValidationSeverity::Critical))->toBe([])
        ->and(rulesHit($results, ValidationSeverity::Warning))->toBe([])
        ->and($this->validator->hasCriticalErrors($project))->toBeFalse()
        ->and($this->validator->summary($project)['can_export'])->toBeTrue();
})->with(DeliveryModel::cases());

it('passes projects whose scope switches parts of the library off', function (array $config): void {
    $project = compiledProject($config);

    $results = $this->validator->validate($project);

    expect(rulesHit($results, ValidationSeverity::Critical))->toBe([]);
})->with([
    'HVAC in the vendor package with a control room' => [['control_room_exists' => true, 'hvac_in_vendor_package' => true]],
    'no switchroom' => [['switchroom_exists' => false]],
    'no substation' => [['substation_exists' => false]],
    'everything switched on' => [[
        'control_room_exists' => true, 'hvac_in_vendor_package' => true, 'fire_in_vendor_package' => true, 'bess_free_issued' => true,
        'oam_building_exists' => true, 'guardhouse_exists' => true, 'site_office_exists' => true, 'ablutions_building_exists' => true,
        'workshop_building_exists' => true, 'fire_pump_house_exists' => true, 'potable_water_exists' => true, 'cctv_security_exists' => true,
        'access_control_exists' => true, 'public_address_exists' => true, 'site_comms_backbone_exists' => true, 'lightning_protection_site_exists' => true,
    ]],
]);

it('notes when a package waits on a fallback output', function (): void {
    $project = compiledProject(['control_room_exists' => true, 'hvac_in_vendor_package' => true]);

    $note = collect($this->validator->validate($project))
        ->where('ruleClass', SuppressedOutputRule::class)
        ->first();

    expect($note->severity)->toBe(ValidationSeverity::Info)
        ->and($note->message)->toContain("waits on 'bod_concept_ga_approved' because 'hvac_basis_approved' isn't produced");
});

it('still flags a site with neither a switchroom nor a substation to energise from', function (): void {
    $project = compiledProject(['switchroom_exists' => false, 'substation_exists' => false]);

    $results = collect($this->validator->validate($project));

    expect($results->where('ruleClass', SuppressedOutputRule::class)->where('severity', ValidationSeverity::Critical)->pluck('context.required_output_key')->all())
        ->toBe(['switchroom_energised']);
});

it('flags an activity with no predecessor and no constraint as an open start', function (): void {
    $project = compiledProject();
    $stray = strayActivity($project, 'STRAY-1');
    Relationship::create([
        'project_id' => $project->id, 'predecessor_id' => $stray->id,
        'successor_id' => activityCoded($project, 'MS-FINAL-HANDOVER-001')->id,
        'relationship_type' => RelationshipType::FinishToStart, 'lag_days' => 0, 'origin' => RelationshipOrigin::Manual,
    ]);

    $results = collect($this->validator->validate($project));

    expect($results->where('ruleClass', OpenStartRule::class)->pluck('context.activity_code')->all())->toBe(['STRAY-1'])
        ->and($results->where('ruleClass', OpenFinishRule::class))->toBeEmpty();
});

it('flags a task with no successor, but not the Project Complete milestone', function (): void {
    $project = compiledProject();
    $stray = strayActivity($project, 'STRAY-2');
    Relationship::create([
        'project_id' => $project->id, 'predecessor_id' => activityCoded($project, 'MS-NTP-001')->id,
        'successor_id' => $stray->id,
        'relationship_type' => RelationshipType::FinishToStart, 'lag_days' => 0, 'origin' => RelationshipOrigin::Manual,
    ]);

    $results = collect($this->validator->validate($project));

    expect($results->where('ruleClass', OpenFinishRule::class)->pluck('context.activity_code')->all())->toBe(['STRAY-2']);
});

it('flags a milestone with no links at all as orphaned', function (): void {
    $project = compiledProject();
    strayActivity($project, 'STRAY-MS', milestone: true);

    $results = collect($this->validator->validate($project));

    expect($results->where('ruleClass', OrphanMilestoneRule::class)->pluck('context.activity_code')->all())->toBe(['STRAY-MS']);
});

it('flags an included-elsewhere package with no containing package', function (): void {
    $project = compiledProject();
    packageFor($project, 'civil_fence_30')->update(['ownership_mode' => OwnershipMode::IncludedElsewhere, 'included_in_package_id' => null]);

    $results = collect($this->validator->validate($project));

    expect($results->where('ruleClass', InvalidPackageCombinationRule::class)->first()?->message)
        ->toContain('Fence and Security Civil Design 30%');
});

it('flags a deselected package whose output an active package needs', function (): void {
    $project = preparePackages(makeProject());
    packageFor($project, 'civil_fence_30')->update(['selected' => false]);
    compileSchedule($project);

    $results = collect($this->validator->validate($project));

    expect($results->where('ruleClass', SuppressedOutputRule::class)->pluck('context.required_output_key')->all())
        ->toContain('civil_30_fence_approved')
        ->and($this->validator->hasCriticalErrors($project))->toBeTrue();
});

it('notes package completions that only roll up to a phase closure', function (): void {
    $project = compiledProject();

    $notes = collect($this->validator->validate($project))
        ->where('ruleClass', MissingCompletionRollupRule::class)
        ->where('severity', ValidationSeverity::Info);

    expect($notes->pluck('context.package_template_code'))->toContain('ms_final_handover', 'con_bop_site_ups');
});

it('acknowledges warnings and notes but never critical errors', function (): void {
    $project = compiledProject();
    strayActivity($project, 'STRAY-MS', milestone: true);
    $this->validator->validate($project);

    $acknowledged = $this->validator->acknowledgeWarnings($project);

    expect($acknowledged)->toBeGreaterThan(0)
        ->and(ValidationError::where('project_id', $project->id)->where('severity', ValidationSeverity::Critical)->where('resolved', false)->count())->toBeGreaterThan(0)
        ->and($this->validator->hasCriticalErrors($project))->toBeTrue()
        ->and($this->validator->summary($project))->toMatchArray(['warning' => 0, 'info' => 0, 'can_export' => false]);
});

it('ignores a critical error someone marked as resolved', function (): void {
    $project = compiledProject();
    strayActivity($project, 'STRAY-MS', milestone: true);
    $this->validator->validate($project);

    ValidationError::where('project_id', $project->id)->update(['resolved' => true]);

    expect($this->validator->hasCriticalErrors($project))->toBeTrue();
});
