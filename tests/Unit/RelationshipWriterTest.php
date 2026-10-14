<?php

declare(strict_types=1);

use App\Enums\RelationshipOrigin;
use App\Enums\RelationshipType;
use App\Models\ActivityInstance;
use App\Models\Relationship;
use App\Services\Bess\RelationshipWriter;

beforeEach(function (): void {
    $this->project = makeProject();
    $wbs = $this->project->wbsNodes()->where('level', 1)->value('id');
    $this->ids = collect(range(1, 4))->map(fn (int $n) => ActivityInstance::create([
        'project_id' => $this->project->id, 'wbs_node_id' => $wbs, 'activity_code' => "A{$n}",
        'name' => "Activity {$n}", 'duration_days' => 1, 'is_milestone' => false, 'sequence' => $n,
    ])->id)->all();
});

it('writes queued links in one go and links a pair only once', function (): void {
    [$a, $b, $c] = $this->ids;
    $writer = new RelationshipWriter($this->project);

    expect($writer->link($a, $b))->toBeTrue()
        ->and($writer->link($a, $b))->toBeFalse()
        ->and($writer->link($b, $c, RelationshipType::StartToStart, 5, RelationshipOrigin::Dependency))->toBeTrue()
        ->and($writer->hasSuccessor($a))->toBeTrue()
        ->and($writer->hasSuccessor($c))->toBeFalse()
        ->and(Relationship::count())->toBe(0);

    $writer->flush();

    $rel = Relationship::where('predecessor_id', $b)->first();
    expect(Relationship::count())->toBe(2)
        ->and($rel->relationship_type)->toBe(RelationshipType::StartToStart)
        ->and($rel->lag_days)->toBe(5)
        ->and($rel->origin)->toBe(RelationshipOrigin::Dependency)
        ->and($writer->link($a, $b))->toBeFalse();
});

it('knows about links already in the database and can remove them', function (): void {
    [$a, $b, $c, $d] = $this->ids;
    $first = new RelationshipWriter($this->project);
    $first->link($a, $b);
    $first->flush();

    $writer = new RelationshipWriter($this->project);
    expect($writer->linked($a, $b))->toBeTrue()
        ->and($writer->link($a, $b))->toBeFalse();

    $writer->link($c, $d);
    $writer->unlink($c, $d);
    $writer->unlink($a, $b);
    $writer->flush();

    expect(Relationship::count())->toBe(0);
});
