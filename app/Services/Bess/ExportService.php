<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Bess\Export\ExcelExportStrategy;
use App\Services\Bess\Export\ExportStrategy;
use App\Services\Bess\Export\XerExportStrategy;
use RuntimeException;

class ExportService
{
    public function __construct(
        private readonly ValidationService $validationService,
    ) {}

    /**
     * Export a project schedule. Validates first — blocks on critical errors.
     *
     * @throws RuntimeException if critical validation errors exist
     */
    public function export(Project $project, string $format = 'xer', ?string $outputPath = null): string
    {
        if ($this->validationService->hasCriticalErrors($project)) {
            throw new RuntimeException(
                'Cannot export: project has unresolved critical validation errors.'
            );
        }

        $strategy = $this->resolveStrategy($format);
        $outputPath ??= storage_path('app/exports');

        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $filePath = $strategy->export($project, $outputPath);

        $project->update([
            'status' => ProjectStatus::Exported,
            'exported_at' => now(),
        ]);

        return $filePath;
    }

    /**
     * Get the strategy for a format string.
     */
    public function resolveStrategy(string $format): ExportStrategy
    {
        return match ($format) {
            'xer' => new XerExportStrategy(),
            'excel', 'csv' => new ExcelExportStrategy(),
            default => throw new RuntimeException("Unsupported export format: {$format}"),
        };
    }

    /**
     * List available export formats.
     */
    public function availableFormats(): array
    {
        return [
            ['value' => 'xer', 'label' => 'Primavera P6 XER', 'extension' => 'xer'],
            ['value' => 'csv', 'label' => 'Excel / CSV', 'extension' => 'csv'],
        ];
    }
}