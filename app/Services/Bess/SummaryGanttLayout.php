<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Models\Project;
use Carbon\Carbon;

/**
 * Computes positions and labels for the Summary Gantt.
 *
 * Single source of truth shared by the SVG blade view and the
 * PowerPoint exporter so the on-screen and .pptx output match.
 *
 * Layout: milestone labels at the top, then milestone diamonds, then a
 * coloured timeline band carrying the year labels, then the bar plot area.
 * Vertical leader lines drop from each milestone through the timeline
 * band into the bar area so phase status at each milestone is readable.
 */
final class SummaryGanttLayout
{
    public const CANVAS_WIDTH = 1100;
    public const CANVAS_HEIGHT = 360;

    public const PLOT_LEFT = 30;
    public const PLOT_RIGHT = 1070;
    public const PLOT_WIDTH = self::PLOT_RIGHT - self::PLOT_LEFT;

    public const MILESTONE_LABEL_TOP = 10;
    public const MILESTONE_Y = 60;
    public const TIMELINE_TOP = 75;
    public const TIMELINE_BOTTOM = 100;

    public const BAR_HEIGHT = 26;
    public const ROW_STRIDE = 36;
    public const BAR_DESIGN_Y = 115;
    public const BAR_CIVIL_Y = 151;
    public const BAR_MECHANICAL_Y = 187;
    public const BAR_ELECTRICAL_Y = 223;
    public const BAR_COMMISSIONING_Y = 259;
    public const PLOT_BOTTOM = 320;

    public const COLOUR_DESIGN = '#6366f1';
    public const COLOUR_CIVIL = '#92400e';
    public const COLOUR_MECHANICAL = '#2563eb';
    public const COLOUR_ELECTRICAL = '#dc2626';
    public const COLOUR_COMMISSIONING = '#059669';

    public const COLOUR_TIMELINE_BAND = '#1e3a8a';
    public const COLOUR_LEADER_LINE = '#94a3b8';

    /**
     * @return array{
     *   has_window: bool,
     *   start: ?Carbon, finish: ?Carbon, total_days: ?int,
     *   bars: array<int, array{name:string,start:Carbon,finish:Carbon,duration_days:int,x:float,width:float,y:int,colour:string,label:string}>,
     *   milestones: array<int, array{name:string,date:Carbon,x:float,colour:string,short:string}>,
     *   axis_ticks: array<int, array{label:string,x:float,major:bool}>,
     *   warnings: array<int, string>,
     * }
     */
    public function build(Project $project): array
    {
        $ntp = $project->start_date;
        $designFreeze = $project->design_freeze_date;
        $siteMob = $project->site_mobilisation_date;
        $civilFinish = $project->civil_completion_date;
        $mechStart = $project->mechanical_install_start_date;
        $mechFinish = $project->mechanical_install_finish_date;
        $elecStart = $project->electrical_install_start_date;
        $elecFinish = $project->electrical_install_finish_date;
        $mechComp = $project->mechanical_completion_date;
        $energisation = $project->energisation_date;
        $commFinish = $project->commissioning_finish_date;
        $pc = $project->practical_completion_date;

        $allDates = array_filter([
            $ntp, $designFreeze, $siteMob, $civilFinish,
            $mechStart, $mechFinish, $elecStart, $elecFinish,
            $mechComp, $energisation, $commFinish, $pc,
        ]);

        if (count($allDates) < 2) {
            return [
                'has_window' => false,
                'start' => $ntp ? Carbon::parse($ntp) : null,
                'finish' => null,
                'total_days' => null,
                'bars' => [],
                'milestones' => [],
                'axis_ticks' => [],
                'warnings' => ['Enter at least two key dates to render the Gantt.'],
            ];
        }

        $start = collect($allDates)->map(fn ($d) => Carbon::parse($d))->min();
        $finish = collect($allDates)->map(fn ($d) => Carbon::parse($d))->max();
        $totalDays = max(1, (int) $start->diffInDays($finish));

        $x = fn (Carbon $d): float => self::PLOT_LEFT + ($start->diffInDays($d) / $totalDays) * self::PLOT_WIDTH;

        $warnings = [];
        $bars = [];

        $barDefs = [
            ['name' => 'Design', 'start' => $ntp, 'finish' => $designFreeze, 'y' => self::BAR_DESIGN_Y, 'colour' => self::COLOUR_DESIGN, 'label' => 'Design', 'startLabel' => 'NTP', 'finishLabel' => 'Design Freeze'],
            ['name' => 'Civil', 'start' => $siteMob, 'finish' => $civilFinish, 'y' => self::BAR_CIVIL_Y, 'colour' => self::COLOUR_CIVIL, 'label' => 'Civil', 'startLabel' => 'Site Mobilisation', 'finishLabel' => 'Civil Completion'],
            ['name' => 'Mechanical', 'start' => $mechStart, 'finish' => $mechFinish, 'y' => self::BAR_MECHANICAL_Y, 'colour' => self::COLOUR_MECHANICAL, 'label' => 'Mechanical', 'startLabel' => 'Mech Install Start', 'finishLabel' => 'Mech Install Finish'],
            ['name' => 'Electrical', 'start' => $elecStart, 'finish' => $elecFinish, 'y' => self::BAR_ELECTRICAL_Y, 'colour' => self::COLOUR_ELECTRICAL, 'label' => 'Electrical', 'startLabel' => 'Electrical Install Start', 'finishLabel' => 'Electrical Install Finish'],
            ['name' => 'Commissioning', 'start' => $mechComp, 'finish' => $commFinish, 'y' => self::BAR_COMMISSIONING_Y, 'colour' => self::COLOUR_COMMISSIONING, 'label' => 'Commissioning', 'startLabel' => 'Mechanical Completion', 'finishLabel' => 'Commissioning Finish'],
        ];

        foreach ($barDefs as $def) {
            if (! $def['start'] || ! $def['finish']) {
                $warnings[] = "{$def['label']} band needs {$def['startLabel']} and {$def['finishLabel']}.";

                continue;
            }
            $bs = Carbon::parse($def['start']);
            $bf = Carbon::parse($def['finish']);
            if (! $bf->greaterThan($bs)) {
                $warnings[] = "{$def['label']} band: {$def['finishLabel']} must be after {$def['startLabel']}.";

                continue;
            }
            $bars[] = [
                'name' => $def['name'],
                'start' => $bs,
                'finish' => $bf,
                'duration_days' => (int) $bs->diffInDays($bf),
                'x' => $x($bs),
                'width' => max(2.0, $x($bf) - $x($bs)),
                'y' => $def['y'],
                'colour' => $def['colour'],
                'label' => $def['label'],
            ];
        }

        $milestoneDefs = [
            ['name' => 'NTP', 'short' => 'NTP', 'date' => $ntp, 'colour' => '#111827'],
            ['name' => 'Site Mobilisation', 'short' => 'Site Mob', 'date' => $siteMob, 'colour' => '#1f2937'],
            ['name' => 'Mechanical Completion', 'short' => 'Mech Comp', 'date' => $mechComp, 'colour' => '#7c3aed'],
            ['name' => 'Energisation', 'short' => 'Energise', 'date' => $energisation, 'colour' => '#d97706'],
            ['name' => 'Commissioning Finish', 'short' => 'Comm End', 'date' => $commFinish, 'colour' => '#0891b2'],
            ['name' => 'Practical Completion', 'short' => 'PC', 'date' => $pc, 'colour' => '#be123c'],
        ];

        $milestones = [];
        foreach ($milestoneDefs as $m) {
            if (! $m['date']) {
                continue;
            }
            $d = Carbon::parse($m['date']);
            $milestones[] = [
                'name' => $m['name'],
                'short' => $m['short'],
                'date' => $d,
                'x' => $x($d),
                'colour' => $m['colour'],
            ];
        }

        return [
            'has_window' => true,
            'start' => $start,
            'finish' => $finish,
            'total_days' => $totalDays,
            'bars' => $bars,
            'milestones' => $milestones,
            'axis_ticks' => $this->buildAxisTicks($start, $finish, $x),
            'warnings' => $warnings,
        ];
    }

    /**
     * Year-only ticks for the timeline band. The first tick is anchored
     * at the window start; subsequent ticks fall on January 1 of each
     * new year. Each renderer computes x from `date` against its own
     * plot bounds.
     *
     * @return array<int, array{label:string,date:Carbon,x:float,major:bool,year:int}>
     */
    private function buildAxisTicks(Carbon $start, Carbon $finish, \Closure $x): array
    {
        $ticks = [];

        // First tick: always at window start (using start year as label).
        $ticks[] = [
            'label' => $start->format('Y'),
            'date' => $start->copy(),
            'x' => $x($start),
            'major' => true,
            'year' => (int) $start->format('Y'),
        ];

        // Subsequent year boundaries.
        $cursor = $start->copy()->startOfYear()->addYear();
        while ($cursor->lessThanOrEqualTo($finish)) {
            $ticks[] = [
                'label' => $cursor->format('Y'),
                'date' => $cursor->copy(),
                'x' => $x($cursor),
                'major' => true,
                'year' => (int) $cursor->format('Y'),
            ];
            $cursor->addYear();
        }

        return $ticks;
    }
}