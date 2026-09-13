<?php

declare(strict_types=1);

namespace App\Services\Bess\Export;

use App\Models\Project;

interface ExportStrategy
{
    /**
     * Export the project schedule data.
     *
     * @return string  The file path of the generated export
     */
    public function export(Project $project, string $outputPath): string;

    /**
     * Get the file extension for this export format.
     */
    public function extension(): string;

    /**
     * Get the MIME type for this export format.
     */
    public function mimeType(): string;
}