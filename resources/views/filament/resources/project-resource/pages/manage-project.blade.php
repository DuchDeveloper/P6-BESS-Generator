<x-filament-panels::page>
    {{-- Build in progress: poll until it finishes --}}
    @if ($this->record->isBuilding())
        @php
            $waitingSince = $this->record->build_queued_at;
            $noWorker = $this->record->build_status === \App\Enums\BuildStatus::Queued
                && $waitingSince !== null && $waitingSince->lt(now()->subSeconds(30));
        @endphp
        <div wire:poll.2s="checkBuild"
             class="flex items-start gap-3 rounded-xl border border-primary-300 bg-primary-50 p-4 dark:border-primary-500/40 dark:bg-primary-500/10">
            <x-filament::loading-indicator class="mt-0.5 h-5 w-5 text-primary-600 dark:text-primary-400" />
            <div class="text-sm">
                <p class="font-semibold text-gray-950 dark:text-white">
                    Building schedule — {{ $this->record->build_stage ?? $this->record->build_status->label() }}
                </p>
                <p class="mt-0.5 text-gray-600 dark:text-gray-400">
                    Started {{ ($this->record->build_started_at ?? $waitingSince)?->diffForHumans() }}.
                    You can keep working; this page updates when the build finishes.
                </p>
                @if ($noWorker)
                    <p class="mt-2 text-warning-700 dark:text-warning-400">
                        Still waiting to start. Builds run on the queue — make sure a worker is running
                        (<code class="font-mono">php artisan queue:work</code>), or set
                        <code class="font-mono">QUEUE_CONNECTION=sync</code> in .env to build without one.
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Project Overview --}}
    <x-filament::section>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</div>
                <div class="text-lg font-bold">{{ $this->record->code }}</div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Delivery Model</div>
                <div class="text-lg">{{ $this->record->delivery_model->label() }}</div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</div>
                <x-filament::badge :color="match($this->record->status->value) {
                    'draft' => 'gray',
                    'configured' => 'info',
                    'compiled' => 'warning',
                    'validated' => 'success',
                    'exported' => 'primary',
                    default => 'gray',
                }">
                    {{ $this->record->status->label() }}
                </x-filament::badge>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</div>
                <div class="text-lg">{{ $this->record->start_date->format('Y-m-d') }}</div>
            </div>
        </div>

        @if ($this->record->build_status !== null && ! $this->record->isBuilding())
            @php
                $built = $this->record->build_status === \App\Enums\BuildStatus::Succeeded;
                $canExport = (bool) ($this->record->build_summary['can_export'] ?? false);
            @endphp
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-200 pt-4 text-sm dark:border-white/10">
                <span class="font-medium text-gray-500 dark:text-gray-400">Last build</span>
                <x-filament::badge :color="! $built ? 'danger' : ($canExport ? 'success' : 'warning')" size="sm">
                    {{ $this->record->build_status->label() }}
                </x-filament::badge>
                <span class="text-gray-600 dark:text-gray-400">
                    {{ $this->record->build_finished_at?->diffForHumans() }}@if ($this->record->build_message) · {{ $this->record->build_message }}@endif
                </span>
            </div>
        @endif
    </x-filament::section>

    {{-- Statistics --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $this->record->packageInstances()->where('selected', true)->where('applicable', true)->count() }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Active Packages</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                    {{ $this->record->providerResolutions()->count() }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Resolutions</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                    {{ $this->record->activityInstances()->count() }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Activities</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-info-600 dark:text-info-400">
                    {{ $this->record->relationships()->count() }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Relationships</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                @php
                    $criticalCount = $this->record->validationErrors()->where('severity', 'critical')->count();
                    $warningCount = $this->record->validationErrors()->where('severity', 'warning')->where('resolved', false)->count();
                @endphp
                <div class="text-2xl font-bold {{ $criticalCount > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                    {{ $criticalCount }} / {{ $warningCount }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Critical / Warnings</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Topology Summary --}}
    @if($this->record->zoneConfigurations()->count() > 0)
        <x-filament::section heading="Topology Configuration" icon="heroicon-o-cpu-chip" collapsible>
            <div class="space-y-3">
                @foreach($this->record->zoneConfigurations()->with('blockConfigurations')->orderBy('zone_number')->get() as $zone)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                        <div class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ $zone->displayLabel() }} &mdash;
                            <span class="text-sm text-gray-500">{{ $zone->total_batteries }} batteries, {{ $zone->total_groups }} groups, {{ $zone->block_count }} blocks</span>
                        </div>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                            @foreach($zone->blockConfigurations as $block)
                                <div class="rounded border p-2 text-sm {{ $block->is_configured ? 'border-gray-200 dark:border-gray-700' : 'border-warning-400 bg-warning-50 dark:border-warning-600 dark:bg-warning-950' }}">
                                    <div class="font-medium">{{ $block->displayLabel() }}</div>
                                    <div class="text-gray-600 dark:text-gray-400">
                                        {{ $block->battery_count }} batteries &times; {{ $block->battery_group_size }}/group
                                        = {{ $block->full_battery_group_count }} groups
                                        @if($block->has_battery_partial_group)
                                            + 1 partial ({{ $block->battery_remainder }})
                                            <x-filament::badge :color="$block->battery_remainder_decision === 'accepted' ? 'success' : 'warning'" size="sm">
                                                {{ $block->battery_remainder_decision }}
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-500 text-xs">
                                        PCS: {{ $block->pcs_count }} | SUT: {{ $block->sut_count }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @php
                    $totalConfigured = $this->record->blockConfigurations()->sum('battery_count');
                    $target = $this->record->total_battery_count;
                @endphp
                @if($target !== null)
                    <div class="flex items-center gap-2 p-2 rounded {{ (int)$totalConfigured === $target ? 'bg-success-50 dark:bg-success-950 text-success-700 dark:text-success-300' : 'bg-danger-50 dark:bg-danger-950 text-danger-700 dark:text-danger-300' }}">
                        <span class="font-medium">Battery Total: {{ $totalConfigured }} / {{ $target }}</span>
                        @if((int)$totalConfigured === $target)
                            <span>&#10003; Reconciled</span>
                        @else
                            <span>&#10007; Mismatch ({{ $target - (int)$totalConfigured }} difference)</span>
                        @endif
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- Validation Errors (critical errors can't be acknowledged, so always show) --}}
    @php
        $openIssues = $this->record->validationErrors()
            ->where(fn ($q) => $q->where('resolved', false)->orWhere('severity', 'critical'));
        $openIssueCount = (clone $openIssues)->count();
    @endphp
    @if($openIssueCount > 0)
        <x-filament::section heading="Validation Issues" icon="heroicon-o-exclamation-triangle" collapsible collapsed
            :description="$openIssueCount > 30 ? 'Showing 30 of ' . $openIssueCount : null">
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($openIssues->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")->limit(30)->get() as $error)
                    @php
                        $tone = match ($error->severity->value) {
                            'critical' => ['danger', 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950'],
                            'warning' => ['warning', 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950'],
                            default => ['info', 'border-info-300 bg-info-50 dark:border-info-700 dark:bg-info-950'],
                        };
                    @endphp
                    <div class="flex items-start gap-2 rounded-lg border p-3 {{ $tone[1] }}">
                        <x-filament::badge :color="$tone[0]" size="sm">
                            {{ strtoupper($error->severity->value) }}
                        </x-filament::badge>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $error->rule_class }}</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">{{ $error->message }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Package Instances Table --}}
    {{ $this->table }}
</x-filament-panels::page>