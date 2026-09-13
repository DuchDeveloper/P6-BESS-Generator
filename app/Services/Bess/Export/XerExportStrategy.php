<?php

declare(strict_types=1);

namespace App\Services\Bess\Export;

use App\Enums\ConstraintType;
use App\Models\ActivityInstance;
use App\Models\Project;
use App\Models\Relationship;
use App\Models\WbsNode;
use Carbon\Carbon;

/**
 * Export to Primavera P6 XER format.
 * Matches P6 24.12 export format exactly.
 */
class XerExportStrategy implements ExportStrategy
{
    private const TAB = "\t";
    private const NL = "\n";

    private int $projectId;
    private int $calendarId = 178;
    private int $obsId = 636;

    public function export(Project $project, string $outputPath): string
    {
        $this->projectId = $project->id + 4500; // Avoid low IDs that P6 may reserve

        $filePath = $outputPath . '/' . $project->code . '.xer';

        $lines = [];

        // ERMHDR
        $lines[] = $this->line('ERMHDR', '24.12', Carbon::now()->format('Y-m-d'), 'Project', 'ADMIN', 'admin', 'dbxDatabaseNoName', 'Project Management', 'USD');

        // CURRTYPE
        $lines[] = $this->line('%T', 'CURRTYPE');
        $lines[] = $this->line('%F', 'curr_id', 'decimal_digit_cnt', 'curr_symbol', 'decimal_symbol', 'digit_group_symbol', 'pos_curr_fmt_type', 'neg_curr_fmt_type', 'curr_type', 'curr_short_name', 'group_digit_cnt', 'base_exch_rate');
        $lines[] = $this->line('%R', '1', '2', '$', '.', ',', '#1.1', '(#1.1)', 'US Dollar', 'USD', '3', '1');

        // FINTMPL
        $lines[] = $this->line('%T', 'FINTMPL');
        $lines[] = $this->line('%F', 'fintmpl_id', 'fintmpl_name', 'default_flag');
        $lines[] = $this->line('%R', '1', 'Calendar', 'Y');

        // OBS
        $lines[] = $this->line('%T', 'OBS');
        $lines[] = $this->line('%F', 'obs_id', 'parent_obs_id', 'guid', 'seq_num', 'obs_name', 'obs_descr');
        $lines[] = $this->line('%R', '540', '', '', '0', 'Enterprise', '');
        $lines[] = $this->line('%R', (string)$this->obsId, '540', $this->guid(), '0', 'BESS Projects', '');

        // CALENDAR
        $this->addCalendarTable($lines, $project);

        // PROJECT
        $this->addProjectTable($lines, $project);

        // PROJWBS
        $this->addWbsTable($lines, $project);

        // TASK
        $this->addActivityTable($lines, $project);

        // TASKPRED
        $this->addRelationshipTable($lines, $project);

        $lines[] = '%E';

        file_put_contents($filePath, implode(self::NL, $lines) . self::NL);

        return $filePath;
    }

    public function extension(): string
    {
        return 'xer';
    }

    public function mimeType(): string
    {
        return 'application/octet-stream';
    }

    private function line(string ...$fields): string
    {
        return implode(self::TAB, $fields);
    }

    private function guid(): string
    {
        return base64_encode(random_bytes(12));
    }

    private function dt(string $date): string
    {
        return $date . ' 00:00';
    }

    private function sanitize(string $value, int $max = 120): string
    {
        return substr(preg_replace('/[\t\r\n]/', ' ', $value), 0, $max);
    }

    private function addCalendarTable(array &$lines, Project $project): void
    {
        $calendar = $project->calendar;
        $hoursPerDay = 8;
        $workDays = $calendar?->workdays_per_week ?? 5;
        $weekHours = $workDays * $hoursPerDay;

        // Build P6-compatible calendar data with DaysOfWeek structure
        // Mon-Fri working (08:00-16:00), Sat-Sun off
        $clndrData = '(0||CalendarData()((0||DaysOfWeek()('
            . '(0||1()())' // Sunday - non-working
            . '(0||2()((0||0(f|16:00|s|08:00)())))' // Monday
            . '(0||3()((0||0(f|16:00|s|08:00)())))' // Tuesday
            . '(0||4()((0||0(f|16:00|s|08:00)())))' // Wednesday
            . '(0||5()((0||0(f|16:00|s|08:00)())))' // Thursday
            . '(0||6()((0||0(f|16:00|s|08:00)())))'; // Friday

        if ($workDays >= 6) {
            $clndrData .= '(0||7()((0||0(f|16:00|s|08:00)())))'; // Saturday working
        } else {
            $clndrData .= '(0||7()())'; // Saturday non-working
        }

        $clndrData .= '))(0||Exceptions()())))';

        $lines[] = $this->line('%T', 'CALENDAR');
        $lines[] = $this->line('%F', 'clndr_id', 'default_flag', 'clndr_name', 'proj_id', 'base_clndr_id', 'last_chng_date', 'clndr_type', 'day_hr_cnt', 'week_hr_cnt', 'month_hr_cnt', 'year_hr_cnt', 'rsrc_private', 'clndr_data');
        $lines[] = $this->line('%R',
            (string)$this->calendarId, 'Y',
            $calendar?->name ?? 'Standard Work Week',
            '', '', // proj_id and base_clndr_id empty for global calendar
            '2000-05-23 16:14', 'CA_Base',
            (string)$hoursPerDay, (string)$weekHours,
            (string)($weekHours * 4), (string)($weekHours * 52),
            'N', $clndrData
        );
    }

    private function addProjectTable(array &$lines, Project $project): void
    {
        $startDate = $this->dt($project->start_date->format('Y-m-d'));
        $now = $this->dt(Carbon::now()->format('Y-m-d'));
        $shortName = $this->sanitize(substr($project->code, 0, 20));

        $lines[] = $this->line('%T', 'PROJECT');
        $lines[] = $this->line('%F',
            'proj_id', 'fy_start_month_num', 'rsrc_self_add_flag', 'allow_complete_flag',
            'rsrc_multi_assign_flag', 'checkout_flag', 'project_flag', 'step_complete_flag',
            'cost_qty_recalc_flag', 'batch_sum_flag', 'name_sep_char', 'def_complete_pct_type',
            'proj_short_name', 'acct_id', 'orig_proj_id', 'source_proj_id', 'base_type_id',
            'clndr_id', 'sum_base_proj_id', 'task_code_base', 'task_code_step', 'priority_num',
            'wbs_max_sum_level', 'strgy_priority_num', 'last_checksum', 'critical_drtn_hr_cnt',
            'def_cost_per_qty', 'last_recalc_date', 'plan_start_date', 'plan_end_date',
            'scd_end_date', 'add_date', 'last_tasksum_date', 'fcst_start_date',
            'def_duration_type', 'task_code_prefix', 'guid', 'def_qty_type', 'add_by_name',
            'web_local_root_path', 'proj_url', 'def_rate_type', 'add_act_remain_flag',
            'act_this_per_link_flag', 'def_task_type', 'act_pct_link_flag',
            'critical_path_type', 'task_code_prefix_flag', 'def_rollup_dates_flag',
            'use_project_baseline_flag', 'rem_target_link_flag', 'reset_planned_flag',
            'allow_neg_act_flag', 'sum_assign_level', 'last_fin_dates_id',
            'fintmpl_id', 'last_baseline_update_date', 'cr_external_key',
            'apply_actuals_date', 'location_id', 'last_schedule_date',
            'loaded_scope_level', 'export_flag', 'new_fin_dates_id',
            'baselines_to_export', 'baseline_names_to_export', 'next_data_date',
            'close_period_flag', 'sum_refresh_date', 'trsrcsum_loaded', 'sumtask_loaded'
        );
        $lines[] = $this->line('%R',
            (string)$this->projectId, '1', 'Y', 'Y',
            'Y', 'N', 'Y', 'N',
            'N', 'Y', '.', 'CP_Drtn',
            $shortName, '', '', '', '',
            (string)$this->calendarId, '', '1000', '10', '10',
            '2', '500', '', '0',
            '0.0000', $now, $startDate, '',
            '', $now, '', '',
            'DT_FixedDUR2', 'A', $this->guid(), 'QT_Hour', 'ADMIN',
            '', '', 'COST_PER_QTY', 'N',
            'Y', 'TT_Task', 'Y',
            'CT_TotFloat', 'Y', 'Y',
            'Y', 'Y', 'N',
            'N', 'SL_Taskrsrc', '',
            '1', '', '',
            '', '', '',
            '7', 'Y', '',
            '', '', '',
            '', '', ''
        );
    }

    private function addWbsTable(array &$lines, Project $project): void
    {
        $nodes = WbsNode::where('project_id', $project->id)
            ->orderBy('level')
            ->orderBy('sort_order')
            ->get();

        $shortName = $this->sanitize(substr($project->code, 0, 20));

        $lines[] = $this->line('%T', 'PROJWBS');
        $lines[] = $this->line('%F',
            'wbs_id', 'proj_id', 'obs_id', 'seq_num', 'est_wt',
            'proj_node_flag', 'sum_data_flag', 'status_code',
            'wbs_short_name', 'wbs_name', 'phase_id', 'parent_wbs_id',
            'ev_user_pct', 'ev_etc_user_value', 'orig_cost',
            'indep_remain_total_cost', 'ann_dscnt_rate_pct', 'dscnt_period_type',
            'indep_remain_work_qty', 'anticip_start_date', 'anticip_end_date',
            'ev_compute_type', 'ev_etc_compute_type', 'guid',
            'tmpl_guid', 'plan_open_state'
        );

        $seqNum = 1;
        foreach ($nodes as $node) {
            $isRoot = $node->parent_id === null;
            // Offset WBS IDs to avoid conflicts
            $wbsId = $node->id + 28000;
            $parentWbsId = $isRoot ? '' : (string)($node->parent_id + 28000);

            $lines[] = $this->line('%R',
                (string)$wbsId, (string)$this->projectId, (string)$this->obsId,
                (string)$seqNum, '1',
                $isRoot ? 'Y' : 'N', 'N', 'WS_Open',
                $isRoot ? $shortName : $this->sanitize(substr($node->code, 0, 20)),
                $this->sanitize($node->name), '', $parentWbsId,
                '0', '0', '0.0000',
                '0.0000', '', '',
                '', '', '',
                'EC_Cmp_pct', 'EE_Rem_hr', $this->guid(),
                '', ''
            );
            $seqNum++;
        }
    }

    private function addActivityTable(array &$lines, Project $project): void
    {
        $activities = ActivityInstance::where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        $now = $this->dt(Carbon::now()->format('Y-m-d'));
        $hoursPerDay = $project->calendar?->workdays_per_week ? 8 : 8;

        $lines[] = $this->line('%T', 'TASK');
        $lines[] = $this->line('%F',
            'task_id', 'proj_id', 'wbs_id', 'clndr_id',
            'phys_complete_pct', 'rev_fdbk_flag', 'est_wt',
            'lock_plan_flag', 'auto_compute_act_flag', 'complete_pct_type',
            'task_type', 'duration_type', 'status_code',
            'task_code', 'task_name', 'rsrc_id',
            'total_float_hr_cnt', 'free_float_hr_cnt',
            'remain_drtn_hr_cnt', 'act_work_qty', 'remain_work_qty',
            'target_work_qty', 'target_drtn_hr_cnt',
            'target_equip_qty', 'act_equip_qty', 'remain_equip_qty',
            'cstr_date', 'act_start_date', 'act_end_date',
            'late_start_date', 'late_end_date',
            'expect_end_date', 'early_start_date', 'early_end_date',
            'restart_date', 'reend_date',
            'target_start_date', 'target_end_date',
            'rem_late_start_date', 'rem_late_end_date',
            'cstr_type', 'priority_type',
            'suspend_date', 'resume_date',
            'float_path', 'float_path_order',
            'guid', 'tmpl_guid',
            'cstr_date2', 'cstr_type2',
            'driving_path_flag', 'act_this_per_work_qty', 'act_this_per_equip_qty',
            'external_early_start_date', 'external_late_end_date',
            'create_date', 'update_date',
            'create_user', 'update_user',
            'location_id', 'crt_path_num'
        );

        foreach ($activities as $activity) {
            $durationHours = $activity->duration_days * $hoursPerDay;
            $taskType = $activity->is_milestone ? 'TT_Mile' : 'TT_Task';
            $wbsId = $activity->wbs_node_id + 28000;
            // Offset task IDs
            $taskId = $activity->id + 50000;

            $cstrType = $this->mapConstraintType($activity->constraint_type);
            $cstrDate = $activity->constraint_date
                ? $this->dt($activity->constraint_date->format('Y-m-d'))
                : '';

            $lines[] = $this->line('%R',
                (string)$taskId, (string)$this->projectId, (string)$wbsId, (string)$this->calendarId,
                '0', 'N', '1',
                'N', 'N', 'CP_Drtn',
                $taskType, 'DT_FixedDUR2', 'TK_NotStart',
                $this->sanitize($activity->activity_code), $this->sanitize($activity->name), '',
                '', '',
                (string)$durationHours, '0', '0',
                '0', (string)$durationHours,
                '0', '0', '0',
                $cstrDate, '', '',
                '', '',
                '', '', '',
                '', '',
                '', '',
                '', '',
                $cstrType, 'PT_Normal',
                '', '',
                '', '',
                $this->guid(), '',
                '', '',
                'N', '0', '0',
                '', '',
                $now, $now,
                'admin', 'admin',
                '', ''
            );
        }
    }

    private function addRelationshipTable(array &$lines, Project $project): void
    {
        $relationships = Relationship::where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        if ($relationships->isEmpty()) {
            return;
        }

        $lines[] = $this->line('%T', 'TASKPRED');
        $lines[] = $this->line('%F', 'task_pred_id', 'task_id', 'pred_task_id', 'proj_id', 'pred_proj_id', 'pred_type', 'lag_hr_cnt', 'comments', 'float_path', 'aref', 'arls');

        $hoursPerDay = 8;
        $predId = 1;
        foreach ($relationships as $rel) {
            $lagHours = $rel->lag_days * $hoursPerDay;
            $predType = 'PR_' . $rel->relationship_type->value;
            // Apply same task ID offset
            $successorId = $rel->successor_id + 50000;
            $predecessorId = $rel->predecessor_id + 50000;

            $lines[] = $this->line('%R',
                (string)$predId,
                (string)$successorId, (string)$predecessorId,
                (string)$this->projectId, (string)$this->projectId,
                $predType, (string)$lagHours,
                '', '', '', ''
            );
            $predId++;
        }
    }

    private function mapConstraintType(?ConstraintType $type): string
    {
        if ($type === null || $type === ConstraintType::None) {
            return '';
        }

        return match ($type) {
            ConstraintType::StartOnOrAfter => 'CS_ALAP',
            ConstraintType::FinishOnOrBefore => 'CS_FNLTON',
            ConstraintType::MandatoryStart => 'CS_MSO',
            ConstraintType::MandatoryFinish => 'CS_MFO',
            default => '',
        };
    }
}