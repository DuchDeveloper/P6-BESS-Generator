<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BuildStatus;
use App\Models\Project;
use App\Services\Bess\ScheduleBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Builds one project's schedule in the background. One build per project can
 * be queued at a time; the outcome is recorded on the project by
 * ScheduleBuilder rather than thrown, so a failed build never breaks the page
 * that queued it (with a sync queue the job runs inside that request).
 */
class BuildProjectSchedule implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $projectId) {}

    public function uniqueId(): string
    {
        return (string) $this->projectId;
    }

    public function handle(ScheduleBuilder $builder): void
    {
        $project = Project::find($this->projectId);

        if ($project === null) {
            return;
        }

        try {
            $builder->build($project);
        } catch (Throwable $e) {
            // Already recorded on the project; keep the details in the log.
            report($e);
        }
    }

    /**
     * The worker gave up on the job (e.g. it timed out): don't leave the
     * project looking as if it's still building.
     */
    public function failed(?Throwable $exception): void
    {
        Project::whereKey($this->projectId)
            ->whereIn('build_status', [BuildStatus::Queued->value, BuildStatus::Running->value])
            ->update([
                'build_status' => BuildStatus::Failed->value,
                'build_stage' => null,
                'build_message' => 'The build stopped before it finished' . ($exception ? ': ' . $exception->getMessage() : '.'),
                'build_finished_at' => now(),
            ]);
    }
}
