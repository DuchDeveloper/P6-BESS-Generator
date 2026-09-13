<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Models\BasisOfSchedule;
use App\Models\Project;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders a BasisOfSchedule into a Microsoft Word (.docx) document.
 *
 * Mirrors the section structure of the HTML template in
 * resources/views/bess/basis-of-schedule.blade.php.
 */
final class BasisOfScheduleWordService
{
    public function download(Project $project, BasisOfSchedule $bos): StreamedResponse
    {
        $word = $this->build($project, $bos);
        $filename = sprintf('BoS-%s-v%d.docx', $project->code ?? $project->id, $bos->version);

        // PhpWord uses ZipArchive internally, which requires a seekable file
        // handle — writing directly to php://output produces a corrupt .docx.
        // Save to a temp file, stream it back, then clean up.
        $tmpPath = tempnam(sys_get_temp_dir(), 'bos_') . '.docx';
        IOFactory::createWriter($word, 'Word2007')->save($tmpPath);

        return response()->streamDownload(function () use ($tmpPath) {
            readfile($tmpPath);
            @unlink($tmpPath);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Length' => (string) filesize($tmpPath),
        ]);
    }

    private function build(Project $project, BasisOfSchedule $bos): PhpWord
    {
        $word = new PhpWord();
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);

        $word->addTitleStyle(1, ['bold' => true, 'size' => 22], ['spaceAfter' => 120]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 14, 'color' => '1F2937'],
            ['spaceBefore' => 280, 'spaceAfter' => 100, 'borderBottomSize' => 8, 'borderBottomColor' => '1F2937']);
        $word->addTitleStyle(3, ['bold' => true, 'size' => 12, 'color' => '374151'],
            ['spaceBefore' => 180, 'spaceAfter' => 80]);

        $word->setDefaultParagraphStyle(['spaceAfter' => 100, 'lineHeight' => 1.3]);

        $section = $word->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1100,
            'marginRight' => 1100,
        ]);

        $s = $bos->sections;
        $header = $s['header'] ?? [];
        $topology = $s['topology'] ?? [];
        $scope = $s['scope'] ?? [];
        $delivery = $s['delivery'] ?? [];
        $milestones = $s['milestones'] ?? [];
        $counts = $s['counts'] ?? [];
        $calendar = $s['calendar'] ?? [];
        $schedule = $s['schedule'] ?? [];
        $longLeads = $s['long_leads'] ?? [];
        $sequencing = $s['sequencing'] ?? [];

        // ── Title block ──────────────────────────────────────────
        $section->addText('Basis of Schedule', ['bold' => true, 'size' => 26]);
        $section->addText(
            sprintf(
                '%s · Code %s · Version %d · %s',
                $header['name'] ?? $project->name,
                $header['code'] ?? '—',
                $bos->version,
                $bos->generated_at?->toDateString() ?? ''
            ),
            ['italic' => true, 'color' => '666666', 'size' => 11],
            ['spaceAfter' => 320]
        );

        // ── Contents ─────────────────────────────────────────────
        $section->addText('Contents', ['bold' => true, 'size' => 12]);
        $contents = [
            'Project Header',
            'Schedule Envelope',
            'Scope Narrative',
            'Delivery Approach',
            'Topology',
            'Scope Inclusions',
            'Sequencing & Logic',
            'Calendar',
            'Long-Lead Procurement',
            'Key Milestones',
            'WBS Summary',
            'Key Assumptions',
            'Schedule Risks',
            'Exclusions',
        ];
        foreach ($contents as $i => $entry) {
            $section->addListItem(($i + 1) . '. ' . $entry, 0, null, null, ['spaceAfter' => 40]);
        }

        // ── 1. Project Header ────────────────────────────────────
        $section->addTitle('1. Project Header', 2);
        $this->addKeyValueTable($section, [
            'Project' => $header['name'] ?? '—',
            'Code' => $header['code'] ?? '—',
            'Client' => $header['client'] ?? '—',
            'Description' => $header['description'] ?? '—',
            'Start date' => $header['start_date'] ?? '—',
            'Delivery model' => $delivery['model_label'] ?? $delivery['model'] ?? '—',
            'Status' => $header['status'] ?? '—',
        ]);

        // ── 2. Schedule Envelope ─────────────────────────────────
        $section->addTitle('2. Schedule Envelope', 2);
        if (! ($schedule['compiled'] ?? false)) {
            $section->addText(
                'Schedule has not been compiled yet — dates and durations will populate after the Compile step.',
                ['italic' => true]
            );
            $this->addKeyValueTable($section, [
                'Planned start' => $schedule['planned_start'] ?? '—',
            ]);
        } else {
            $duration = ($schedule['duration_days'] ?? '—') . ' days';
            if (! empty($schedule['duration_months'])) {
                $duration .= ' (~' . $schedule['duration_months'] . ' months)';
            }
            $this->addKeyValueTable($section, [
                'Planned start' => $schedule['planned_start'] ?? '—',
                'Planned finish' => $schedule['planned_finish'] ?? '—',
                'Total duration' => $duration,
                'Activities' => number_format($schedule['activity_count'] ?? 0),
                'Milestones' => number_format($schedule['milestone_count'] ?? 0),
                'Relationships' => number_format($schedule['relationship_count'] ?? 0),
            ]);
        }

        // ── 3. Scope Narrative ───────────────────────────────────
        $section->addTitle('3. Scope Narrative', 2);
        $this->addMarkdown($section, $s['scope_narrative'] ?? null);

        // ── 4. Delivery Approach ─────────────────────────────────
        $section->addTitle('4. Delivery Approach', 2);
        $this->addMarkdown($section, $s['delivery_narrative'] ?? null);

        // ── 5. Topology ──────────────────────────────────────────
        $section->addTitle('5. Topology', 2);
        $this->addKeyValueTable($section, [
            'Zones' => $topology['zones'] ?? '—',
            'Blocks per zone' => $topology['blocks_per_zone'] ?? '—',
            'Total blocks' => $topology['total_blocks'] ?? '—',
            'Battery groups per block' => $topology['battery_groups_per_block'] ?? '—',
            'Batteries per group' => $topology['batteries_per_group'] ?? '—',
            'PCS groups per block' => $topology['pcs_groups_per_block'] ?? '—',
            'PCS per group' => $topology['pcs_per_group'] ?? '—',
            'SUTs per block' => $topology['suts_per_block'] ?? '—',
            'Total batteries' => $topology['total_batteries'] ?? '—',
            'Architecture' => $topology['architecture'] ?? '—',
        ]);

        // ── 6. Scope Inclusions ──────────────────────────────────
        $section->addTitle('6. Scope Inclusions', 2);
        $section->addTitle('Core HV / MV plant', 3);
        $coreFlags = ['switchroom', 'control_room', 'transformer', 'substation', 'scada',
            'bess_free_issued', 'hvac_in_vendor_package', 'fire_in_vendor_package'];
        $coreRows = [];
        foreach ($coreFlags as $k) {
            $coreRows[ucwords(str_replace('_', ' ', $k))] = ($scope[$k] ?? false) ? 'Yes' : 'No';
        }
        $this->addKeyValueTable($section, $coreRows);

        $groupLabels = [
            'buildings' => 'Buildings',
            'site_civil' => 'Site Civil',
            'site_ei' => 'Site E&I',
            'safety_fire' => 'Safety & Fire',
        ];
        foreach ($groupLabels as $key => $label) {
            if (empty($scope[$key])) {
                continue;
            }
            $section->addTitle($label, 3);
            $rows = [];
            foreach ($scope[$key] as $name => $on) {
                $rows[ucwords(str_replace('_', ' ', $name))] = $on ? 'Yes' : 'No';
            }
            $this->addKeyValueTable($section, $rows);
        }

        // ── 7. Sequencing & Logic ────────────────────────────────
        $section->addTitle('7. Sequencing & Logic', 2);
        $blockMode = $sequencing['block_sequencing_mode_label'] ?? $sequencing['block_sequencing_mode'] ?? '—';
        if (($sequencing['block_sequencing_lag_days'] ?? 0) > 0) {
            $blockMode .= ' (lag ' . $sequencing['block_sequencing_lag_days'] . ' d)';
        }
        $this->addKeyValueTable($section, [
            'Zone strategy' => 'Zones run in parallel — independent work fronts.',
            'Block sequencing mode' => $blockMode,
            'Mechanical install gate' => $sequencing['mech_install_gate'] ?? 'Civil sign-off + bulk equipment delivery',
        ]);
        $this->addMarkdown($section, $s['sequencing_rationale'] ?? null);

        // ── 8. Calendar ──────────────────────────────────────────
        $section->addTitle('8. Calendar', 2);
        $this->addKeyValueTable($section, [
            'Calendar' => $calendar['name'] ?? 'Default',
            'Workdays per week' => $calendar['workdays_per_week'] ?? '—',
            'Holidays defined' => $calendar['holiday_count'] ?? 0,
        ]);

        // ── 9. Long-Lead Procurement ─────────────────────────────
        $section->addTitle('9. Long-Lead Procurement', 2);
        if (! empty($longLeads)) {
            $rows = [];
            foreach ($longLeads as $ll) {
                $rows[] = [
                    $ll['code'] ?? '',
                    $ll['name'] ?? '',
                    $ll['ownership'] ?? '—',
                ];
            }
            $this->addDataTable($section, ['Code', 'Package', 'Ownership'], $rows, [1500, 5500, 2200]);
        } else {
            $section->addText('No procurement packages selected.', ['italic' => true]);
        }

        // ── 10. Key Milestones ───────────────────────────────────
        $section->addTitle('10. Key Milestones', 2);
        if (! empty($milestones)) {
            $rows = [];
            foreach ($milestones as $m) {
                $rows[] = [
                    (string) ($m['sequence'] ?? ''),
                    $m['name'] ?? '',
                    $m['output_key'] ?? '',
                ];
            }
            $this->addDataTable($section, ['Seq', 'Name', 'Output Key'], $rows, [800, 4500, 3900]);
        } else {
            $section->addText('No energisation milestones configured.', ['italic' => true]);
        }

        // ── 11. WBS Summary ──────────────────────────────────────
        $section->addTitle('11. WBS Summary', 2);
        $wbs = $s['wbs'] ?? [];
        if (! empty($wbs)) {
            $rows = [];
            foreach ($wbs as $n) {
                $rows[] = [(string) ($n['code'] ?? ''), (string) ($n['name'] ?? ''), (string) ($n['level'] ?? '')];
            }
            $this->addDataTable($section, ['Code', 'Name', 'Level'], $rows, [1800, 5800, 1600]);
        }

        $section->addTitle('Package counts', 3);
        $countRows = [];
        foreach ($counts as $k => $v) {
            $countRows[ucwords(str_replace('_', ' ', (string) $k))] = (string) $v;
        }
        $this->addKeyValueTable($section, $countRows);

        // ── 12. Key Assumptions ──────────────────────────────────
        $section->addTitle('12. Key Assumptions', 2);
        $this->addMarkdown($section, $s['assumptions'] ?? null);

        // ── 13. Schedule Risks ───────────────────────────────────
        $section->addTitle('13. Schedule Risks', 2);
        $this->addMarkdown($section, $s['risks'] ?? null);

        // ── 14. Exclusions ───────────────────────────────────────
        $section->addTitle('14. Exclusions', 2);
        $this->addMarkdown($section, $s['exclusions'] ?? null);

        return $word;
    }

    /**
     * @param  array<string, mixed>  $rows  Label => value
     */
    private function addKeyValueTable(Section $section, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $tableStyle = [
            'borderSize' => 4,
            'borderColor' => 'E5E7EB',
            'cellMargin' => 80,
        ];
        $section->addTableStyle('kv_' . spl_object_id($section) . '_' . count($rows), $tableStyle);
        $table = $section->addTable($tableStyle);
        $labelStyle = ['bold' => true, 'color' => '4B5563'];
        $cellShade = ['bgColor' => 'F9FAFB'];

        foreach ($rows as $label => $value) {
            $table->addRow();
            $table->addCell(3200, $cellShade)->addText((string) $label, $labelStyle);
            $table->addCell(6400)->addText((string) $value);
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, int>  $widths  twips per column
     */
    private function addDataTable(Section $section, array $headers, array $rows, array $widths): void
    {
        $tableStyle = [
            'borderSize' => 4,
            'borderColor' => 'E5E7EB',
            'cellMargin' => 80,
        ];
        $table = $section->addTable($tableStyle);

        $headerStyle = ['bold' => true, 'color' => '4B5563'];
        $headerShade = ['bgColor' => 'F9FAFB'];
        $table->addRow();
        foreach ($headers as $i => $h) {
            $table->addCell($widths[$i] ?? 2000, $headerShade)->addText($h, $headerStyle);
        }

        foreach ($rows as $row) {
            $table->addRow();
            foreach ($row as $i => $cell) {
                $table->addCell($widths[$i] ?? 2000)->addText((string) $cell);
            }
        }
    }

    private function addMarkdown(Section $section, ?string $markdown): void
    {
        if ($markdown === null || trim($markdown) === '') {
            $section->addText('Not generated.', ['italic' => true]);
            return;
        }

        $html = (string) Str::markdown($markdown);

        try {
            Html::addHtml($section, $html, false, false);
        } catch (\Throwable $e) {
            // Fallback: drop HTML and write the raw markdown as plain text
            // so the document still generates if the parser chokes.
            $plain = trim(strip_tags($html));
            foreach (preg_split("/\r\n|\n|\r/", $plain) as $line) {
                if (trim($line) !== '') {
                    $section->addText($line);
                }
            }
        }
    }
}