<?php

declare(strict_types=1);

namespace App\Services\Bess\Export;

use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\ProviderResolution;
use App\Models\Relationship;
use App\Models\WbsNode;
use App\Services\Bess\ExportService;

/**
 * Export the compiled schedule graph as JSON — WBS, activities, relationships
 * and output resolutions — for integrations and for diffing compilations.
 */
class JsonExportStrategy implements ExportStrategy
{
    public const FORMAT_VERSION = 'bess-schedule/1';

    public function export(Project $project, string $outputPath): string
    {
        $filePath = $outputPath . '/' . ExportService::fileStem($project) . '.' . $this->extension();

        file_put_contents(
            $filePath,
            json_encode($this->payload($project), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        );

        return $filePath;
    }

    public function extension(): string
    {
        return 'json';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Project $project): array
    {
        $project->loadMissing('calendar');

        $activities = ActivityInstance::where('project_id', $project->id)
            ->with('packageInstance.template')
            ->orderBy('id')
            ->get();
        $codes = $activities->pluck('activity_code', 'id');

        return [
            'format' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'client' => $project->client,
                'delivery_model' => $project->delivery_model->value,
                'start_date' => $project->start_date->format('Y-m-d'),
                'calendar' => $project->calendar ? [
                    'name' => $project->calendar->name,
                    'workdays_per_week' => $project->calendar->workdays_per_week,
                ] : null,
            ],
            'wbs' => WbsNode::where('project_id', $project->id)
                ->orderBy('level')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (WbsNode $node) => [
                    'id' => $node->id,
                    'parent_id' => $node->parent_id,
                    'code' => $node->code,
                    'name' => $node->name,
                    'level' => $node->level,
                    'category' => $node->wbs_category?->value,
                ])
                ->all(),
            'activities' => $activities
                ->map(fn (ActivityInstance $activity) => [
                    'id' => $activity->id,
                    'code' => $activity->activity_code,
                    'name' => $activity->name,
                    'wbs_id' => $activity->wbs_node_id,
                    'package' => $activity->packageInstance?->template?->code,
                    'ownership_mode' => $activity->packageInstance?->ownership_mode->value,
                    'duration_days' => $activity->duration_days,
                    'is_milestone' => $activity->is_milestone,
                    'task_type' => $activity->p6_task_type->value,
                    'constraint' => $activity->constraint_date !== null ? [
                        'type' => $activity->constraint_type?->value,
                        'date' => $activity->constraint_date->format('Y-m-d'),
                    ] : null,
                ])
                ->all(),
            'relationships' => Relationship::where('project_id', $project->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Relationship $rel) => [
                    'predecessor' => $codes->get($rel->predecessor_id),
                    'successor' => $codes->get($rel->successor_id),
                    'type' => $rel->relationship_type->value,
                    'lag_days' => $rel->lag_days,
                    'origin' => $rel->origin?->value,
                ])
                ->all(),
            'outputs' => ProviderResolution::where('project_id', $project->id)
                ->with('providerPackageInstance.template')
                ->orderBy('output_key')
                ->get()
                ->map(fn (ProviderResolution $resolution) => [
                    'output_key' => $resolution->output_key,
                    'resolution' => $resolution->resolution_type->value,
                    'provider_package' => $resolution->providerPackageInstance?->template?->code,
                    'provider_activity' => $codes->get($resolution->provider_activity_instance_id),
                ])
                ->all(),
        ];
    }
}
