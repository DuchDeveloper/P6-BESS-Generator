<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * One engine run per project at a time. Resolving, compiling, validating and
 * exporting all rewrite or read the same project rows, so two people working
 * on the same project must not overlap. Different projects never block each
 * other. Uses the cache store's atomic locks (database, file or redis).
 */
class ProjectLock
{
    /** Longest a run may hold the lock before it's considered abandoned. */
    public const SECONDS = 900;

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     *
     * @throws ProjectBusyException when another run holds the project
     */
    public function run(Project $project, callable $work): mixed
    {
        $lock = Cache::lock(self::key($project), self::SECONDS);

        if (! $lock->get()) {
            throw new ProjectBusyException(
                "Another build or export is running for {$project->code}. Try again when it finishes."
            );
        }

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    private static function key(Project $project): string
    {
        return "bess:project:{$project->id}:engine";
    }
}
