<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\DataTransferObjects\BasisOfScheduleContext;
use App\Enums\WbsCategory;
use App\Models\ActivityInstance;
use App\Models\BasisOfSchedule;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Models\Relationship;
use App\Models\WbsNode;
use App\Services\Bess\Prompts\BasisOfSchedulePrompts;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

/**
 * Generates a Basis of Schedule for a project.
 *
 * Deterministic sections (DB-sourced): header, topology, scope, delivery,
 * milestones, wbs, calendar, schedule envelope, long-leads, sequencing.
 *
 * LLM sections (Prism + Anthropic): scope_narrative, delivery_narrative,
 * sequencing_rationale, assumptions, risks, exclusions.
 *
 * A new versioned BasisOfSchedule row is created per generation so
 * earlier drafts remain browsable.
 */
final class BasisOfScheduleService
{
    public function __construct(
        private readonly BasisOfSchedulePrompts $prompts,
    ) {}

    public function generate(Project $project): BasisOfSchedule
    {
        $context = $this->gatherContext($project);

        $sections = [
            'header' => $context->header,
            'topology' => $context->topology,
            'scope' => $context->scope,
            'delivery' => $context->delivery,
            'milestones' => $context->milestones,
            'wbs' => $context->wbs,
            'counts' => $context->counts,
            'calendar' => $context->calendar,
            'schedule' => $context->schedule,
            'long_leads' => $context->longLeads,
            'sequencing' => $context->sequencing,
        ];

        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'model' => null];

        $llmSections = [
            'scope_narrative' => fn () => $this->prompts->scopeNarrative($context),
            'delivery_narrative' => fn () => $this->prompts->deliveryNarrative($context),
            'sequencing_rationale' => fn () => $this->prompts->sequencingRationale($context),
            'assumptions' => fn () => $this->prompts->assumptions($context),
            'risks' => fn () => $this->prompts->risks($context),
            'exclusions' => fn () => $this->prompts->exclusions($context),
        ];

        foreach ($llmSections as $key => $promptFactory) {
            [$system, $user] = $promptFactory();
            [$text, $u] = $this->callLlm($system, $user, maxTokens: 1500);
            $sections[$key] = $text;
            $this->accumulateUsage($usage, $u);
        }

        $version = ($project->basisOfSchedules()->max('version') ?? 0) + 1;

        return $project->basisOfSchedules()->create([
            'version' => $version,
            'status' => 'draft',
            'sections' => $sections,
            'llm_model' => $usage['model'],
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'project_fingerprint' => $this->fingerprint($project),
            'generated_at' => now(),
        ]);
    }

    // ── Context assembly ─────────────────────────────────────────────

    public function gatherContext(Project $project): BasisOfScheduleContext
    {
        $header = [
            'name' => $project->name,
            'code' => $project->code,
            'client' => $project->client,
            'description' => $project->description,
            'start_date' => optional($project->start_date)->toDateString(),
            'compiled_at' => optional($project->compiled_at)->toDateTimeString(),
            'status' => $project->status?->value,
        ];

        $topology = [
            'zones' => $project->zone_count,
            'blocks_per_zone' => $project->blocks_per_zone,
            'total_blocks' => (int) $project->zone_count * (int) $project->blocks_per_zone,
            'battery_groups_per_block' => $project->battery_groups_per_block,
            'batteries_per_group' => $project->batteries_per_group,
            'pcs_groups_per_block' => $project->pcs_groups_per_block,
            'pcs_per_group' => $project->pcs_per_group,
            'suts_per_block' => $project->suts_per_block,
            'total_batteries' => $project->total_battery_count,
            'architecture' => $project->bess_architecture?->value,
        ];

        $scope = [
            'switchroom' => (bool) $project->switchroom_exists,
            'control_room' => (bool) $project->control_room_exists,
            'transformer' => (bool) $project->transformer_exists,
            'substation' => (bool) $project->substation_exists,
            'scada' => (bool) $project->scada_included,
            'bess_free_issued' => (bool) $project->bess_free_issued,
            'hvac_in_vendor_package' => (bool) $project->hvac_in_vendor_package,
            'fire_in_vendor_package' => (bool) $project->fire_in_vendor_package,
            'buildings' => array_filter([
                'oam_building' => (bool) $project->oam_building_exists,
                'site_office' => (bool) $project->site_office_exists,
                'guardhouse' => (bool) $project->guardhouse_exists,
                'ablutions' => (bool) $project->ablutions_building_exists,
                'workshop' => (bool) $project->workshop_building_exists,
                'fire_pump_house' => (bool) $project->fire_pump_house_exists,
            ]),
            'site_civil' => array_filter([
                'perimeter_fencing' => (bool) $project->perimeter_fencing_exists,
                'access_roads' => (bool) $project->access_roads_exists,
                'site_drainage' => (bool) $project->site_drainage_exists,
                'potable_water' => (bool) $project->potable_water_exists,
            ]),
            'site_ei' => array_filter([
                'external_lighting' => (bool) $project->external_lighting_exists,
                'site_ups' => (bool) $project->site_ups_exists,
                'site_comms_backbone' => (bool) $project->site_comms_backbone_exists,
                'cctv_security' => (bool) $project->cctv_security_exists,
                'access_control' => (bool) $project->access_control_exists,
                'public_address' => (bool) $project->public_address_exists,
            ]),
            'safety_fire' => array_filter([
                'building_fire_system' => (bool) $project->building_fire_system_exists,
                'site_lightning_protection' => (bool) $project->lightning_protection_site_exists,
            ]),
        ];

        $delivery = [
            'model' => $project->delivery_model?->value,
            'model_label' => $project->delivery_model?->label(),
        ];

        $milestones = $project->energisationMilestones
            ->where('selected', true)
            ->sortBy('sequence')
            ->map(fn ($m) => [
                'sequence' => $m->sequence,
                'name' => $m->name,
                'output_key' => $m->output_key,
            ])
            ->values()
            ->all();

        return new BasisOfScheduleContext(
            header: $header,
            topology: $topology,
            scope: $scope,
            delivery: $delivery,
            milestones: $milestones,
            wbs: $this->summariseWbs($project),
            counts: $this->countPackages($project),
            calendar: $this->summariseCalendar($project),
            schedule: $this->summariseSchedule($project),
            longLeads: $this->summariseLongLeads($project),
            sequencing: $this->summariseSequencing($project),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function summariseWbs(Project $project): array
    {
        return WbsNode::query()
            ->where('project_id', $project->id)
            ->whereIn('level', [1, 2])
            ->orderBy('level')
            ->orderBy('sort_order')
            ->get(['code', 'name', 'level', 'parent_id'])
            ->map(fn (WbsNode $n) => [
                'code' => $n->code,
                'name' => $n->name,
                'level' => $n->level,
            ])
            ->all();
    }

    /** @return array<string, int> */
    private function countPackages(Project $project): array
    {
        $counts = [];

        foreach (WbsCategory::cases() as $category) {
            $counts[$category->value] = PackageInstance::query()
                ->where('project_id', $project->id)
                ->where('selected', true)
                ->whereHas('template', fn ($q) => $q->where('wbs_category', $category))
                ->count();
        }

        $counts['total_selected'] = PackageInstance::query()
            ->where('project_id', $project->id)
            ->where('selected', true)
            ->count();

        return $counts;
    }

    /** @return array<string, mixed> */
    private function summariseCalendar(Project $project): array
    {
        $cal = $project->calendar;
        if (! $cal) {
            return ['name' => null, 'workdays_per_week' => null, 'holiday_count' => 0];
        }

        return [
            'name' => $cal->name,
            'workdays_per_week' => $cal->workdays_per_week,
            'holiday_count' => is_array($cal->holidays) ? count($cal->holidays) : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function summariseSchedule(Project $project): array
    {
        $activityCount = ActivityInstance::where('project_id', $project->id)->count();
        $milestoneCount = ActivityInstance::where('project_id', $project->id)->where('is_milestone', true)->count();
        $relationshipCount = Relationship::where('project_id', $project->id)->count();

        if ($activityCount === 0) {
            return [
                'compiled' => false,
                'activity_count' => 0,
                'milestone_count' => 0,
                'relationship_count' => 0,
                'planned_start' => optional($project->start_date)->toDateString(),
                'planned_finish' => null,
                'duration_days' => null,
                'duration_months' => null,
            ];
        }

        $earliestStart = ActivityInstance::where('project_id', $project->id)->min('early_start_date');
        $latestFinish = ActivityInstance::where('project_id', $project->id)->max('early_finish_date');
        $durationDays = null;
        $durationMonths = null;

        if ($earliestStart && $latestFinish) {
            $start = \Carbon\Carbon::parse($earliestStart);
            $finish = \Carbon\Carbon::parse($latestFinish);
            $durationDays = (int) $start->diffInDays($finish);
            $durationMonths = round($durationDays / 30.44, 1);
        }

        return [
            'compiled' => true,
            'activity_count' => $activityCount,
            'milestone_count' => $milestoneCount,
            'relationship_count' => $relationshipCount,
            'planned_start' => $earliestStart ?: optional($project->start_date)->toDateString(),
            'planned_finish' => $latestFinish,
            'duration_days' => $durationDays,
            'duration_months' => $durationMonths,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function summariseLongLeads(Project $project): array
    {
        // Long-lead = procurement packages currently selected.
        return PackageInstance::query()
            ->where('project_id', $project->id)
            ->where('selected', true)
            ->whereHas('template', fn ($q) => $q->where('wbs_category', WbsCategory::Procurement))
            ->with('template:id,code,name,wbs_category')
            ->get()
            ->map(fn ($pi) => [
                'code' => $pi->template?->code,
                'name' => $pi->template?->name,
                'ownership' => $pi->ownership_mode?->value,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function summariseSequencing(Project $project): array
    {
        return [
            'zones_run_in_parallel' => true,
            'block_sequencing_mode' => $project->block_sequencing_mode?->value,
            'block_sequencing_mode_label' => $project->block_sequencing_mode?->label(),
            'block_sequencing_lag_days' => $project->block_sequencing_lag_days,
            'mech_install_gate' => 'Civil sign-off (cure complete) AND bulk equipment delivery for the area',
            'mech_install_gate_per_group' => false,
        ];
    }

    private function fingerprint(Project $project): string
    {
        return hash('sha256', json_encode([
            $project->updated_at?->toIso8601String(),
            $project->compiled_at?->toIso8601String(),
            $project->packageInstances()->where('selected', true)->count(),
            $project->wbsNodes()->count(),
        ]));
    }

    // ── LLM calls ────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: array{prompt_tokens:int, completion_tokens:int, total_tokens:int, model:string}}
     */
    private function callLlm(string $system, string $user, int $maxTokens): array
    {
        $model = config('bess.bos_draft_model', env('BOS_DRAFT_MODEL', 'claude-haiku-4-5-20251001'));

        $response = Prism::text()
            ->using(Provider::Anthropic, $model)
            ->withSystemPrompt($system)
            ->withPrompt($user)
            ->withMaxTokens($maxTokens)
            ->usingTemperature(0.3)
            ->asText();

        $usage = [
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
            'total_tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            'model' => $model,
        ];

        return [$response->text, $usage];
    }

    /**
     * @param  array<string, mixed>  $acc
     * @param  array{prompt_tokens:int, completion_tokens:int, total_tokens:int, model:string}  $u
     */
    private function accumulateUsage(array &$acc, array $u): void
    {
        $acc['prompt_tokens'] += $u['prompt_tokens'];
        $acc['completion_tokens'] += $u['completion_tokens'];
        $acc['total_tokens'] += $u['total_tokens'];
        $acc['model'] = $u['model'];
    }
}