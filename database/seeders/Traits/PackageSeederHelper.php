<?php

declare(strict_types=1);

namespace Database\Seeders\Traits;

use App\Enums\Discipline;
use App\Enums\MaturityLevel;
use App\Enums\OutputType;
use App\Enums\OwnershipMode;
use App\Enums\P6TaskType;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use App\Models\ActivityTemplate;
use App\Models\DependencyRule;
use App\Models\OutputDefinition;
use App\Models\PackageTemplate;

trait PackageSeederHelper
{
    /**
     * Create a standard design package template with the 6-activity pattern:
     *  1. Review previous approval (2d)
     *  2. Develop content activity 1 (varies)
     *  3. Develop content activity 2 (varies)
     *  4. Develop quantities and interfaces (varies)
     *  5. Issue package (1d)
     *  6. Package Approved (0d milestone) ← output
     */
    protected function createDesignPackage(array $config): PackageTemplate
    {
        $template = PackageTemplate::create([
            'code' => $config['code'],
            'name' => $config['name'],
            'type' => $config['type'] ?? PackageType::Design,
            'discipline' => $config['discipline'],
            'wbs_category' => $config['wbs_category'] ?? WbsCategory::Design,
            'construction_wbs_group' => $config['construction_wbs_group'] ?? null,
            'maturity_level' => $config['maturity_level'] ?? null,
            'ownership_mode_default' => $config['ownership_mode_default'] ?? OwnershipMode::Internal,
            'is_optional' => $config['is_optional'] ?? true,
            'is_conditional' => $config['is_conditional'] ?? false,
            'condition_flag' => $config['condition_flag'] ?? null,
            'is_topology_driven' => $config['is_topology_driven'] ?? false,
            'has_dynamic_predecessors' => $config['has_dynamic_predecessors'] ?? false,
            'dynamic_predecessor_maturity' => $config['dynamic_predecessor_maturity'] ?? null,
            'sort_order' => $config['sort_order'] ?? 0,
        ]);

        $durations = $config['durations'] ?? [2, 5, 5, 3, 1, 0];
        $activityNames = $config['activity_names'] ?? [
            "Review previous approval",
            "Develop {$config['name']} content",
            "Develop {$config['name']} details",
            "Develop quantities and interfaces",
            "Issue {$config['name']}",
            "{$config['name']} Approved",
        ];

        foreach ($activityNames as $i => $name) {
            $seq = $i + 1;
            $isMilestone = ($seq === count($activityNames) && ($durations[$i] ?? 0) === 0);

            ActivityTemplate::create([
                'package_template_id' => $template->id,
                'sequence' => $seq,
                'name' => $name,
                'duration_days' => $durations[$i] ?? 1,
                'is_milestone' => $isMilestone,
                'predecessor_sequence' => $seq > 1 ? $seq - 1 : null,
                'p6_task_type' => $isMilestone ? P6TaskType::FinishMilestone : P6TaskType::Task,
            ]);
        }

        if (isset($config['output_key'])) {
            OutputDefinition::create([
                'package_template_id' => $template->id,
                'output_key' => $config['output_key'],
                'output_name' => $config['output_name'] ?? $activityNames[count($activityNames) - 1],
                'output_type' => $config['output_type'] ?? OutputType::ApprovalMilestone,
                'produced_by_sequence' => count($activityNames),
            ]);
        }

        if (isset($config['depends_on'])) {
            $this->attachDependencies($template, $config['depends_on']);
        }

        return $template;
    }

    /**
     * Create a full maturity chain (30% → 60% → 90% → IFC) for a design discipline.
     * Each level depends on the previous level's output + the entry anchor.
     */
    protected function createMaturityChain(array $chainConfig): array
    {
        $templates = [];
        $levels = [
            '30' => ['maturity' => MaturityLevel::Thirty, 'durations' => [2, 5, 5, 3, 1, 0]],
            '60' => ['maturity' => MaturityLevel::Sixty, 'durations' => [2, 8, 8, 5, 1, 0]],
            '90' => ['maturity' => MaturityLevel::Ninety, 'durations' => [2, 10, 10, 5, 1, 0]],
            'ifc' => ['maturity' => MaturityLevel::Ifc, 'durations' => [2, 5, 5, 3, 1, 0]],
        ];

        $previousOutputKey = null;

        foreach ($levels as $levelKey => $levelConfig) {
            $code = $chainConfig['code_prefix'] . "_{$levelKey}";
            $name = "{$chainConfig['name']} {$levelConfig['maturity']->label()}";
            $outputKey = $chainConfig['output_prefix'] . "_{$levelKey}_" . $chainConfig['output_suffix'];

            // First level depends on entry anchor; subsequent levels depend on previous output
            $dependsOn = [];
            if ($previousOutputKey) {
                $dependsOn[] = $previousOutputKey;
            } else {
                $dependsOn[] = $chainConfig['entry_anchor'];
            }

            $template = $this->createDesignPackage([
                'code' => $code,
                'name' => $name,
                'discipline' => $chainConfig['discipline'],
                'maturity_level' => $levelConfig['maturity'],
                'durations' => $chainConfig['durations'][$levelKey] ?? $levelConfig['durations'],
                'output_key' => $outputKey,
                'depends_on' => $dependsOn,
                'is_optional' => $chainConfig['is_optional'] ?? true,
                'is_conditional' => $chainConfig['is_conditional'] ?? false,
                'condition_flag' => $chainConfig['condition_flag'] ?? null,
                'sort_order' => $chainConfig['sort_order'] ?? 0,
            ]);

            $templates[$levelKey] = $template;
            $previousOutputKey = $outputKey;
        }

        return $templates;
    }

    /**
     * Create a non-design package with custom activity list.
     * Each activity is: ['name' => ..., 'duration' => ..., 'is_milestone' => false]
     */
    protected function createCustomPackage(array $config, array $activities): PackageTemplate
    {
        $template = PackageTemplate::create([
            'code' => $config['code'],
            'name' => $config['name'],
            'type' => $config['type'] ?? PackageType::Procurement,
            'discipline' => $config['discipline'] ?? Discipline::None,
            'wbs_category' => $config['wbs_category'] ?? WbsCategory::Procurement,
            'construction_wbs_group' => $config['construction_wbs_group'] ?? null,
            'maturity_level' => $config['maturity_level'] ?? null,
            'ownership_mode_default' => $config['ownership_mode_default'] ?? OwnershipMode::Internal,
            'is_optional' => $config['is_optional'] ?? true,
            'is_conditional' => $config['is_conditional'] ?? false,
            'condition_flag' => $config['condition_flag'] ?? null,
            'is_topology_driven' => $config['is_topology_driven'] ?? false,
            'sort_order' => $config['sort_order'] ?? 0,
        ]);

        foreach ($activities as $i => $activity) {
            $seq = $i + 1;
            $isMilestone = $activity['is_milestone'] ?? false;

            ActivityTemplate::create([
                'package_template_id' => $template->id,
                'sequence' => $seq,
                'name' => $activity['name'],
                'duration_days' => $activity['duration'] ?? 1,
                'is_milestone' => $isMilestone,
                'predecessor_sequence' => $seq > 1 ? ($activity['predecessor_sequence'] ?? $seq - 1) : null,
                'p6_task_type' => $isMilestone ? P6TaskType::FinishMilestone : P6TaskType::Task,
            ]);
        }

        if (isset($config['output_key'])) {
            OutputDefinition::create([
                'package_template_id' => $template->id,
                'output_key' => $config['output_key'],
                'output_name' => $config['output_name'] ?? $config['name'] . ' Complete',
                'output_type' => $config['output_type'] ?? OutputType::CompletionMilestone,
                'produced_by_sequence' => count($activities),
            ]);
        }

        if (isset($config['depends_on'])) {
            $this->attachDependencies($template, $config['depends_on']);
        }

        return $template;
    }

    /**
     * Normalise a depends_on payload and persist DependencyRule rows.
     *
     * Accepts any mix of:
     *   - scalar string output_key → mandatory dependency
     *   - ['key' => string, 'mandatory' => bool] → explicit control
     *
     * Examples:
     *   'site_mob_complete'
     *   ['site_mob_complete', 'bod_concept_ga_approved']
     *   ['site_mob_complete', ['key' => 'site_comms_backbone_complete', 'mandatory' => false]]
     */
    protected function attachDependencies(PackageTemplate $template, mixed $dependsOn): void
    {
        $entries = is_string($dependsOn) ? [$dependsOn] : (array) $dependsOn;

        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $key = $entry;
                $mandatory = true;
            } elseif (is_array($entry) && isset($entry['key'])) {
                $key = $entry['key'];
                $mandatory = (bool) ($entry['mandatory'] ?? true);
            } else {
                throw new \InvalidArgumentException(
                    "Invalid depends_on entry for template {$template->code}: " . var_export($entry, true)
                );
            }

            DependencyRule::create([
                'consumer_template_id' => $template->id,
                'required_output_key' => $key,
                'gates_activity_sequence' => 1,
                'is_mandatory' => $mandatory,
            ]);
        }
    }
}