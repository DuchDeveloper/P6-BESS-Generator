<?php

declare(strict_types=1);

use App\Enums\ConstraintType;
use App\Enums\DeliveryModel;
use App\Enums\OwnershipMode;
use App\Enums\P6TaskType;
use App\Enums\RelationshipOrigin;
use App\Models\ActivityInstance;
use App\Models\Relationship;
use App\Services\Bess\GraphCompilerService;

beforeEach(function (): void {
    seedLibrary();
});

it('links each design review to every discipline approval at its maturity', function (): void {
    $project = compiledProject();

    $review = activityCoded($project, 'DM-30-REVIEW-001');

    expect(predecessorCodes($review))->toContain('CIVIL-EQPAD-30-006', 'CIVIL-FENCE-30-006', 'ELEC-P-FEEDER-30-006')
        ->not->toContain('CIVIL-EQPAD-60-006');
});

it('compiles an acyclic graph with one open start and one open finish', function (): void {
    $project = compiledProject();

    $activities = ActivityInstance::where('project_id', $project->id)->get();
    $successorIds = Relationship::where('project_id', $project->id)->pluck('successor_id')->flip();
    $predecessorIds = Relationship::where('project_id', $project->id)->pluck('predecessor_id')->flip();

    $openStarts = $activities->reject(fn ($a) => $successorIds->has($a->id));
    $openFinishes = $activities->reject(fn ($a) => $predecessorIds->has($a->id));

    expect(scheduleIsAcyclic($project))->toBeTrue()
        ->and($openStarts->pluck('activity_code')->all())->toBe(['MS-NTP-001'])
        ->and($openStarts->first()->constraint_type)->toBe(ConstraintType::StartOnOrAfter)
        ->and($openStarts->first()->constraint_date->format('Y-m-d'))->toBe('2026-11-02')
        ->and($openFinishes->pluck('activity_code')->all())->toBe([GraphCompilerService::PROJECT_COMPLETE_CODE]);
});

it('rolls dangling milestones up to phase closures and the phase closures up to Project Complete', function (): void {
    $project = compiledProject();

    $complete = activityCoded($project, GraphCompilerService::PROJECT_COMPLETE_CODE);
    $constructionClosure = activityCoded($project, 'PHASE-CONSTRUCTION-COMPLETE');

    expect($complete->is_milestone)->toBeTrue()
        ->and($complete->p6_task_type)->toBe(P6TaskType::FinishMilestone)
        ->and(predecessorCodes($complete))->toContain('PHASE-CONSTRUCTION-COMPLETE', 'MS-FINAL-HANDOVER-001', 'MS-30-DESIGN-REVIEW-001')
        ->and(predecessorCodes($constructionClosure))->toContain('Z1-B1-CIV-COM')
        ->and($constructionClosure->name)->toBe('Construction Complete');

    // Roll-ups only ever start from milestones.
    $rolledUp = Relationship::where('project_id', $project->id)
        ->where('origin', RelationshipOrigin::Rollup)
        ->with('predecessor')
        ->get();
    expect($rolledUp)->not->toBeEmpty()
        ->and($rolledUp->every(fn (Relationship $r) => $r->predecessor->is_milestone))->toBeTrue();
});

it('connects external packages through their receipt milestones', function (): void {
    $project = compiledProject(['delivery_model' => DeliveryModel::ConstructOnly]);

    $receipt = activityCoded($project, 'BOD-CONCEPT-GA-BM');

    expect($receipt->is_milestone)->toBeTrue()
        ->and(predecessorCodes($receipt))->toContain('MS-NTP-001')
        ->and(Relationship::where('predecessor_id', $receipt->id)->exists())->toBeTrue()
        ->and(predecessorCodes(activityCoded($project, 'CIVIL-DESIGN-INPUTS-BM')))->toContain('BOD-CONCEPT-GA-BM');
});

it('constrains an external receipt that nothing drives to the project start', function (): void {
    $project = preparePackages(makeProject());
    packageFor($project, 'bod_concept_ga')->update(['ownership_mode' => OwnershipMode::External]);
    foreach (['ms_ntp', 'pm_design_mgmt_plan', 'pm_schedule_plan'] as $upstream) {
        packageFor($project, $upstream)->update(['ownership_mode' => OwnershipMode::NotApplicable]);
    }

    compileSchedule($project);
    $receipt = activityCoded($project, 'BOD-CONCEPT-GA-BM');

    expect(predecessorCodes($receipt))->toBe([])
        ->and($receipt->constraint_type)->toBe(ConstraintType::StartOnOrAfter)
        ->and($receipt->constraint_date->format('Y-m-d'))->toBe('2026-11-02');
});

it('gives each receipt milestone of a multi-output external package its own activity code', function (): void {
    $project = preparePackages(makeProject());
    packageFor($project, 'con_tx_hv')->update(['ownership_mode' => OwnershipMode::External]);

    compileSchedule($project);

    $codes = ActivityInstance::where('project_id', $project->id)
        ->where('package_instance_id', packageFor($project, 'con_tx_hv')->id)
        ->pluck('activity_code')
        ->all();

    expect($codes)->toBe(['CON-TX-HV-BM', 'CON-TX-HV-BM2'])
        ->and(ActivityInstance::where('project_id', $project->id)->count())
        ->toBe(ActivityInstance::where('project_id', $project->id)->distinct()->count('activity_code'));
});

it('feeds consumers of an included-elsewhere output from the containing package', function (): void {
    $project = preparePackages(makeProject());
    packageFor($project, 'civil_fence_30')->update([
        'ownership_mode' => OwnershipMode::IncludedElsewhere,
        'included_in_package_id' => packageFor($project, 'civil_road_30')->id,
    ]);

    compileSchedule($project);

    expect(ActivityInstance::where('activity_code', 'like', 'CIVIL-FENCE-30-%')->where('project_id', $project->id)->exists())->toBeFalse()
        ->and(predecessorCodes(activityCoded($project, 'CIVIL-FENCE-60-001')))->toContain('CIVIL-ROAD-30-006');
});

it('produces the same graph when compiled twice', function (): void {
    $project = compiledProject();
    $activities = $project->activityInstances()->count();
    $relationships = $project->relationships()->count();

    app(GraphCompilerService::class)->compile($project);

    expect($project->activityInstances()->count())->toBe($activities)
        ->and($project->relationships()->count())->toBe($relationships);
});

it('compiles every BESS architecture and RMU layout without loops', function (array $config): void {
    $project = compiledProject($config);

    expect(scheduleIsAcyclic($project))->toBeTrue()
        ->and($project->activityInstances()->count())->toBeGreaterThan(500);
})->with([
    'no SUT' => [['bess_architecture' => 'no_sut', 'blocks_per_zone' => 2]],
    'integrated container' => [['bess_architecture' => 'integrated_container', 'containers_per_sut' => 4]],
    'cluster' => [['bess_architecture' => 'cluster', 'blocks_per_zone' => 4, 'pcs_per_sut' => 2, 'blocks_per_cluster_sut' => 2]],
    'per-block RMU ring' => [['rmu_topology' => 'per_block', 'rmu_on_mv_ring' => true, 'blocks_per_zone' => 2]],
    'two-level RMU' => [['rmu_topology' => 'per_sut_and_block', 'blocks_per_zone' => 2]],
    'per-zone RMU' => [['rmu_topology' => 'per_zone', 'zone_count' => 2, 'blocks_per_zone' => 2]],
]);
