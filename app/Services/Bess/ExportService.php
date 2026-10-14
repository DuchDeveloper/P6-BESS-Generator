<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Bess\Export\ExcelExportStrategy;
use App\Services\Bess\Export\ExportStrategy;
use App\Services\Bess\Export\JsonExportStrategy;
use App\Services\Bess\Export\XerExportStrategy;
use Illuminate\Support\Str;
use RuntimeException;

class ExportService
{
    public function __construct(
        private readonly ValidationService $validationService,
        private readonly ProjectLock $lock,
    ) {}

    /**
     * Export a project schedule. Validates the schedule as it stands first and
     * blocks on critical errors (CLAUDE.md §1, §15).
     *
     * Each export without an explicit $outputPath gets its own directory under
     * storage/app/exports, so simultaneous exports never overwrite each other;
     * the caller deletes it once the file is sent (see cleanUp()).
     *
     * @throws ProjectBusyException if a build or another export is running for the project
     * @throws RuntimeException if the schedule isn't compiled or has critical validation errors
     */
    public function export(Project $project, string $format = 'xer', ?string $outputPath = null): string
    {
        $strategy = $this->resolveStrategy($format);

        return $this->lock->run($project, fn (): string => $this->exportUnlocked($project, $strategy, $outputPath));
    }

    /**
     * Delete an export file and, if it was given its own directory, that too.
     */
    public function cleanUp(string $filePath): void
    {
        if (is_file($filePath)) {
            unlink($filePath);
        }

        $directory = dirname($filePath);
        if (str_starts_with($directory, storage_path('app/exports') . DIRECTORY_SEPARATOR) && is_dir($directory) && count(scandir($directory)) === 2) {
            rmdir($directory);
        }
    }

    private function exportUnlocked(Project $project, ExportStrategy $strategy, ?string $outputPath): string
    {
        if (! $project->activityInstances()->exists()) {
            throw new RuntimeException('Cannot export: compile the schedule first.');
        }

        // Never trust an earlier validation run — it may predate the latest
        // compile or ownership change.
        $this->validationService->validate($project);

        if ($this->validationService->hasCriticalErrors($project)) {
            $count = $this->validationService->criticalCount($project);

            throw new RuntimeException(
                "Cannot export: the schedule has {$count} critical validation error(s). Fix them and recompile."
            );
        }

        $outputPath ??= storage_path('app/exports/' . Str::uuid()->toString());

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
     * A safe export filename stem from the project code ("BESS/001" → "BESS-001").
     */
    public static function fileStem(Project $project): string
    {
        $stem = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $project->code), '-.');

        return $stem !== '' ? $stem : 'project-' . $project->id;
    }

    /**
     * Get the strategy for a format string.
     */
    public function resolveStrategy(string $format): ExportStrategy
    {
        return match ($format) {
            'xer' => new XerExportStrategy(),
            'excel', 'csv' => new ExcelExportStrategy(),
            'json' => new JsonExportStrategy(),
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
            ['value' => 'json', 'label' => 'JSON', 'extension' => 'json'],
        ];
    }
}