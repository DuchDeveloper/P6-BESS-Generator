<x-filament-panels::page>
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
                    $criticalCount = $this->record->validationErrors()->where('severity', 'critical')->where('resolved', false)->count();
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

    {{-- Validation Errors --}}
    @if($this->record->validationErrors()->where('resolved', false)->count() > 0)
        <x-filament::section heading="Validation Issues" icon="heroicon-o-exclamation-triangle" collapsible collapsed>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($this->record->validationErrors()->where('resolved', false)->orderBy('severity')->limit(30)->get() as $error)
                    <div class="flex items-start gap-2 rounded-lg border p-3 {{ $error->severity->value === 'critical' ? 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' : 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950' }}">
                        <x-filament::badge :color="$error->severity->value === 'critical' ? 'danger' : 'warning'" size="sm">
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