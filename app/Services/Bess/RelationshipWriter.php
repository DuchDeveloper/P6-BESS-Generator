<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\RelationshipOrigin;
use App\Enums\RelationshipType;
use App\Models\Project;
use App\Models\Relationship;

/**
 * Collects schedule relationships in memory and writes them in batches.
 *
 * A compile creates thousands of links; inserting them one row at a time
 * (plus a lookup for every "link if not already linked") was most of the
 * build time. The writer knows every link already in the database for the
 * project and every link waiting to be written, so duplicate checks never
 * touch the database. Call flush() before anything reads relationships
 * from the database.
 */
final class RelationshipWriter
{
    private const CHUNK = 500;

    /** @var array<string, true> "pred:succ" pairs already in the database */
    private array $stored = [];

    /** @var array<string, array<string, mixed>> rows waiting to be inserted, keyed "pred:succ" */
    private array $pending = [];

    /** @var array<int, true> activity ids with at least one successor */
    private array $hasSuccessor = [];

    public function __construct(private readonly Project $project)
    {
        Relationship::where('project_id', $project->id)
            ->get(['predecessor_id', 'successor_id'])
            ->each(function (Relationship $r): void {
                $this->stored["{$r->predecessor_id}:{$r->successor_id}"] = true;
                $this->hasSuccessor[$r->predecessor_id] = true;
            });
    }

    /**
     * Queue a link. A pair of activities is linked at most once, so this
     * returns false (and does nothing) when they're already linked.
     */
    public function link(
        int $predecessorId,
        int $successorId,
        RelationshipType $type = RelationshipType::FinishToStart,
        int $lagDays = 0,
        RelationshipOrigin $origin = RelationshipOrigin::Internal,
    ): bool {
        if ($this->linked($predecessorId, $successorId)) {
            return false;
        }

        $this->pending["{$predecessorId}:{$successorId}"] = [
            'project_id' => $this->project->id,
            'predecessor_id' => $predecessorId,
            'successor_id' => $successorId,
            'relationship_type' => $type->value,
            'lag_days' => $lagDays,
            'origin' => $origin->value,
        ];
        $this->hasSuccessor[$predecessorId] = true;

        return true;
    }

    public function linked(int $predecessorId, int $successorId): bool
    {
        $pair = "{$predecessorId}:{$successorId}";

        return isset($this->stored[$pair]) || isset($this->pending[$pair]);
    }

    public function hasSuccessor(int $activityId): bool
    {
        return isset($this->hasSuccessor[$activityId]);
    }

    /**
     * Remove a link, whether still queued or already written.
     */
    public function unlink(int $predecessorId, int $successorId): void
    {
        $pair = "{$predecessorId}:{$successorId}";
        unset($this->pending[$pair]);

        if (isset($this->stored[$pair])) {
            Relationship::where('project_id', $this->project->id)
                ->where('predecessor_id', $predecessorId)
                ->where('successor_id', $successorId)
                ->delete();
            unset($this->stored[$pair]);
        }
    }

    /**
     * Write every queued link.
     */
    public function flush(): void
    {
        if ($this->pending === []) {
            return;
        }

        $now = now();
        $rows = array_map(fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now], array_values($this->pending));

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            Relationship::insert($chunk);
        }

        foreach (array_keys($this->pending) as $pair) {
            $this->stored[$pair] = true;
        }

        $this->pending = [];
    }
}
