<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\BuildStatus;
use App\Jobs\BuildProjectSchedule;
use App\Models\Project;
use Throwable;

/**
 * Runs the engine chain after the packages are set up: resolve outputs →
 * compile the graph → validate. This is what a planner re-runs after
 * changing a package's selection or ownership mode.
 *
 * Builds normally run as a queued job; progress and the outcome are recorded
 * on the project (build_*), so the Manage page can show them while it waits.
 */
class ScheduleBuilder
{
    public function __construct(
        private readonly DependencyResolverService $resolver,
        private readonly GraphCompilerService $compiler,
        private readonly ValidationService $validator,
        private readonly ProjectLock $lock,
    ) {}

    /**
     * Queue a build. With a sync queue it has finished by the time this returns.
     */
    public function queue(Project $project): void
    {
        $project->forceFill([
            'build_status' => BuildStatus::Queued,
            'build_stage' => 'Waiting to start',
            'build_message' => null,
            'build_queued_at' => now(),
            'build_started_at' => null,
            'build_finished_at' => null,
        ])->save();

        BuildProjectSchedule::dispatch($project->id);
    }

    /**
     * Build now, recording each stage and the outcome on the project.
     *
     * @return array{unresolved: int, activities: int, relationships: int, critical: int, warning: int, info: int, can_export: bool}
     *
     * @throws ProjectBusyException when another build or export holds the project
     */
    public function build(Project $project): array
    {
        try {
            return $this->lock->run($project, fn (): array => $this->buildLocked($project));
        } catch (ProjectBusyException $e) {
            if ($project->build_status === BuildStatus::Queued) {
                $this->finish($project, BuildStatus::Failed, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * @return array{unresolved: int, activities: int, relationships: int, critical: int, warning: int, info: int, can_export: bool}
     */
    private function buildLocked(Project $project): array
    {
        $project->forceFill([
            'build_status' => BuildStatus::Running,
            'build_message' => null,
            'build_started_at' => now(),
            'build_finished_at' => null,
        ])->save();

        try {
            $this->stage($project, 'Resolving dependencies');
            $unresolved = $this->resolver->resolve($project);

            $this->stage($project, 'Compiling schedule');
            $this->compiler->compile($project);

            $this->stage($project, 'Validating');
            $this->validator->validate($project);

            $summary = [
                'unresolved' => count($unresolved),
                'activities' => $project->activityInstances()->count(),
                'relationships' => $project->relationships()->count(),
            ] + $this->validator->summary($project);
        } catch (Throwable $e) {
            $this->finish($project, BuildStatus::Failed, $e->getMessage());

            throw $e;
        }

        $this->finish(
            $project,
            BuildStatus::Succeeded,
            $summary['can_export']
                ? 'Ready to export.'
                : "{$summary['critical']} critical validation error(s) to fix before export.",
            $summary,
        );

        return $summary;
    }

    private function stage(Project $project, string $stage): void
    {
        $project->forceFill(['build_stage' => $stage])->save();
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    private function finish(Project $project, BuildStatus $status, string $message, ?array $summary = null): void
    {
        $project->forceFill([
            'build_status' => $status,
            'build_stage' => null,
            'build_message' => $message,
            'build_summary' => $summary,
            'build_finished_at' => now(),
        ])->save();
    }
}
