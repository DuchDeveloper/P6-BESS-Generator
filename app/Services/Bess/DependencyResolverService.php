<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Enums\OwnershipMode;
use App\Enums\ResolutionType;
use App\Models\DependencyRule;
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
        $unresolved = $this->createResolutions($project, $activePackages, $providerMap, $dynamicDeps, $this->notRequiredOutputs($activePackages));

        return $unresolved;
    }

    /**
     * Build a map of output_key → PackageInstance that provides it.
     *
     * Ownership mode decides who provides an output (CLAUDE.md §6):
     *  - internal / external: the package itself;
     *  - included_elsewhere: the containing package, which does the work;
     *  - not_applicable: nobody — the output is not required, so it is left
     *    out of the map and consumers drop the dependency.
     */
    private function buildProviderMap(Collection $activePackages): array
    {
        $map = [];

        foreach ($activePackages as $instance) {
            $provider = match ($instance->ownership_mode) {
                OwnershipMode::NotApplicable => null,
                OwnershipMode::IncludedElsewhere => $activePackages->firstWhere('id', $instance->included_in_package_id),
                default => $instance,
            };

            // A containing package that is itself included elsewhere or not
            // applicable can't do the work either.
            if ($provider === null || ! in_array($provider->ownership_mode, [OwnershipMode::Internal, OwnershipMode::External], true)) {
                continue;
            }

            foreach ($instance->template->outputDefinitions as $output) {
                $map[$output->output_key] = [
                    'package_instance' => $provider,
                    'output_definition' => $output,
                    'resolution_type' => $provider->ownership_mode === OwnershipMode::External
                        ? ResolutionType::External
                        : ResolutionType::Internal,
                ];
            }
        }

        return $map;
    }

    /**
     * Output keys whose package is marked not_applicable: nothing provides
     * them and nothing needs to.
     *
     * @return array<string, true>
     */
    private function notRequiredOutputs(Collection $activePackages): array
    {
        return $activePackages
            ->filter(fn (PackageInstance $pkg) => $pkg->ownership_mode === OwnershipMode::NotApplicable)
            ->flatMap(fn (PackageInstance $pkg) => $pkg->template->outputDefinitions->pluck('output_key'))
            ->mapWithKeys(fn (string $key) => [$key => true])
            ->all();
    }

    /**
     * Dynamic predecessors for design management packages (CLAUDE.md §7.9):
     * each review depends on every selected discipline package at its
     * maturity level. Shared with the graph compiler, which links them.
     *
     * Expects packages loaded with template.outputDefinitions.
     *
     * @return array<int, list<string>> consumer package instance id → output keys
     */
    public function gatherDynamicPredecessors(Collection $activePackages): array
    {
        $dynamicDeps = [];

        foreach ($activePackages as $instance) {
            $template = $instance->template;

            if (!$template->has_dynamic_predecessors || $template->dynamic_predecessor_maturity === null) {
                continue;
            }

            if ($instance->ownership_mode === OwnershipMode::NotApplicable) {
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
        array $notRequired,
    ): array {
        $unresolved = [];
        $resolutions = [];

        // Resolve static dependencies from dependency_rules. A package marked
        // not_applicable is suppressed entirely, so its inputs aren't needed.
        foreach ($activePackages as $instance) {
            if ($instance->ownership_mode === OwnershipMode::NotApplicable) {
                continue;
            }

            foreach ($instance->template->dependencyRules as $rule) {
                $this->resolveOutputKey(
                    $project,
                    $rule->required_output_key,
                    $providerMap,
                    $resolutions,
                    $unresolved,
                    $notRequired,
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
                    $notRequired,
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
        array $notRequired,
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
            // Provided by a package marked not_applicable — not required.
            if (isset($notRequired[$outputKey])) {
                return;
            }

            // Check if the output is from a deselected or not applicable package
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