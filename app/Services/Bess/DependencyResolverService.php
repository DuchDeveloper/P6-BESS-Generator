<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\OwnershipMode;
use App\Enums\ResolutionType;
use App\Models\DependencyRule;
use App\Models\OutputDefinition;
use App\Models\PackageInstance;
use App\Models\Project;
use App\Models\ProviderResolution;
use Illuminate\Support\Collection;

class DependencyResolverService
{
    /**
     * Resolve all output_key providers for a project.
     * Returns unresolved output keys (if any) for validation.
     */
    public function resolve(Project $project): array
    {
        // Clear previous resolutions
        ProviderResolution::where('project_id', $project->id)->delete();

        $activePackages = PackageInstance::where('project_id', $project->id)
            ->where('selected', true)
            ->where('applicable', true)
            ->with(['template.outputDefinitions', 'template.dependencyRules'])
            ->get();

        // Step 1: Build the provider map — which package instance provides each output_key
        $providerMap = $this->buildProviderMap($activePackages);

        // Step 2: Resolve dynamic predecessors for design management packages
        $dynamicDeps = $this->gatherDynamicPredecessors($activePackages);

        // Step 3: Create provider resolution records
        $unresolved = $this->createResolutions($project, $activePackages, $providerMap, $dynamicDeps);

        return $unresolved;
    }

    /**
     * Build a map of output_key → PackageInstance that provides it.
     */
    private function buildProviderMap(Collection $activePackages): array
    {
        $map = [];

        foreach ($activePackages as $instance) {
            foreach ($instance->template->outputDefinitions as $output) {
                $map[$output->output_key] = [
                    'package_instance' => $instance,
                    'output_definition' => $output,
                    'resolution_type' => $instance->ownership_mode === OwnershipMode::External
                        ? ResolutionType::External
                        : ResolutionType::Internal,
                ];
            }
        }

        return $map;
    }

    /**
     * Gather dynamic predecessors for design management packages.
     * These packages depend on all selected discipline packages at a matching maturity level.
     */
    private function gatherDynamicPredecessors(Collection $activePackages): array
    {
        $dynamicDeps = [];

        foreach ($activePackages as $instance) {
            $template = $instance->template;

            if (!$template->has_dynamic_predecessors || $template->dynamic_predecessor_maturity === null) {
                continue;
            }

            $targetMaturity = $template->dynamic_predecessor_maturity;

            // Find all active packages at the same maturity level (excluding management packages)
            $predecessorOutputKeys = $activePackages
                ->filter(function (PackageInstance $pkg) use ($targetMaturity, $template) {
                    $pkgTemplate = $pkg->template;
                    return $pkgTemplate->maturity_level === $targetMaturity
                        && $pkgTemplate->id !== $template->id
                        && !$pkgTemplate->has_dynamic_predecessors;
                })
                ->flatMap(fn (PackageInstance $pkg) => $pkg->template->outputDefinitions->pluck('output_key'))
                ->values()
                ->all();

            $dynamicDeps[$instance->id] = $predecessorOutputKeys;
        }

        return $dynamicDeps;
    }

    /**
     * Create ProviderResolution records and return list of unresolved output keys.
     */
    private function createResolutions(
        Project $project,
        Collection $activePackages,
        array $providerMap,
        array $dynamicDeps,
    ): array {
        $unresolved = [];
        $resolutions = [];

        // Resolve static dependencies from dependency_rules
        foreach ($activePackages as $instance) {
            foreach ($instance->template->dependencyRules as $rule) {
                $this->resolveOutputKey(
                    $project,
                    $rule->required_output_key,
                    $providerMap,
                    $resolutions,
                    $unresolved,
                    $instance,
                    $rule,
                );
            }
        }

        // Resolve dynamic dependencies
        foreach ($dynamicDeps as $instanceId => $outputKeys) {
            $instance = $activePackages->firstWhere('id', $instanceId);
            foreach ($outputKeys as $outputKey) {
                $this->resolveOutputKey(
                    $project,
                    $outputKey,
                    $providerMap,
                    $resolutions,
                    $unresolved,
                    $instance,
                );
            }
        }

        // Bulk insert resolutions
        foreach ($resolutions as $key => $resolution) {
            ProviderResolution::create($resolution);
        }

        return $unresolved;
    }

    private function resolveOutputKey(
        Project $project,
        string $outputKey,
        array $providerMap,
        array &$resolutions,
        array &$unresolved,
        PackageInstance $consumer,
        ?DependencyRule $rule = null,
    ): void {
        // Skip duplicate resolutions for same output_key
        $resolutionKey = $project->id . ':' . $outputKey;
        if (isset($resolutions[$resolutionKey])) {
            return;
        }

        if (isset($providerMap[$outputKey])) {
            $provider = $providerMap[$outputKey];
            $resolutions[$resolutionKey] = [
                'project_id' => $project->id,
                'output_key' => $outputKey,
                'provider_package_instance_id' => $provider['package_instance']->id,
                'resolution_type' => $provider['resolution_type'],
            ];
        } else {
            // Check if the output is from a not_applicable or deselected package
            $allOutputs = OutputDefinition::pluck('output_key', 'package_template_id');
            $isSuppressed = PackageInstance::where('project_id', $project->id)
                ->whereHas('template.outputDefinitions', fn ($q) => $q->where('output_key', $outputKey))
                ->where(fn ($q) => $q->where('selected', false)->orWhere('applicable', false))
                ->exists();

            if ($isSuppressed && $rule && !$rule->is_mandatory) {
                // Optional dependency from suppressed package — resolve as not needed
                return;
            }

            $resolutions[$resolutionKey] = [
                'project_id' => $project->id,
                'output_key' => $outputKey,
                'provider_package_instance_id' => null,
                'resolution_type' => ResolutionType::Unresolved,
            ];

            $unresolved[] = [
                'output_key' => $outputKey,
                'consumer_package_instance_id' => $consumer->id,
                'consumer_template_code' => $consumer->template->code,
                'is_mandatory' => $rule?->is_mandatory ?? true,
            ];
        }
    }
}