<?php

declare(strict_types=1);

namespace App\Services\Bess\Export;

use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\Relationship;
use App\Services\Bess\ExportService;

/**
 * Export to a CSV/Excel-compatible format.
 * Generates a flat activity list with predecessor references.
 */
class ExcelExportStrategy implements ExportStrategy
{
    public function export(Project $project, string $outputPath): string
    {
        $filePath = $outputPath . '/' . ExportService::fileStem($project) . '.' . $this->extension();

        $handle = fopen($filePath, 'w');

        // Header row
        fputcsv($handle, [
            'Activity Code',
            'Activity Name',
            'Duration (days)',
            'Is Milestone',
            'WBS Code',
            'WBS Name',
            'Package',
            'Predecessors',
            'Constraint Type',
            'Constraint Date',
            'Task Type',
        ], escape: '');

        $activities = ActivityInstance::where('project_id', $project->id)
            ->with(['wbsNode', 'packageInstance.template'])
            ->orderBy('id')
            ->get();

        // Build predecessor lookup
        $activitiesById = $activities->keyBy('id');
        $predecessorMap = Relationship::where('project_id', $project->id)
            ->get()
            ->groupBy('successor_id')
            ->map(function ($rels) use ($activitiesById) {
                return $rels->map(function ($rel) use ($activitiesById) {
                    $pred = $activitiesById->get($rel->predecessor_id);
                    $code = $pred?->activity_code ?? '?';
                    $type = $rel->relationship_type->value;
                    $lag = $rel->lag_days !== 0 ? ($rel->lag_days > 0 ? "+{$rel->lag_days}d" : "{$rel->lag_days}d") : '';

                    return $code . $type . $lag;
                })->join(', ');
            });

        foreach ($activities as $activity) {
            fputcsv($handle, [
                $activity->activity_code,
                $activity->name,
                $activity->duration_days,
                $activity->is_milestone ? 'Yes' : 'No',
                $activity->wbsNode?->code ?? '',
                $activity->wbsNode?->name ?? '',
                $activity->packageInstance?->template?->name ?? '',
                $predecessorMap[$activity->id] ?? '',
                $activity->constraint_type?->value ?? '',
                $activity->constraint_date?->format('Y-m-d') ?? '',
                $activity->p6_task_type->value,
            ], escape: '');
        }

        fclose($handle);

        return $filePath;
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }
}