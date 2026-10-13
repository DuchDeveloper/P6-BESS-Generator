<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Models\Project;

/**
 * Runs the engine chain after the packages are set up: resolve outputs →
 * compile the graph → validate. This is what a planner re-runs after
 * changing a package's selection or ownership mode.
 */
class ScheduleBuilder
{
    public function __construct(
        private readonly DependencyResolverService $resolver,
        private readonly GraphCompilerService $compiler,
        private readonly ValidationService $validator,
    ) {}

    /**
     * @return array{unresolved: int, activities: int, relationships: int, critical: int, warning: int, info: int, can_export: bool}
     */
    public function build(Project $project): array
    {
        $unresolved = $this->resolver->resolve($project);
        $this->compiler->compile($project);
        $this->validator->validate($project);

        return [
            'unresolved' => count($unresolved),
            'activities' => $project->activityInstances()->count(),
            'relationships' => $project->relationships()->count(),
        ] + $this->validator->summary($project);
    }
}
