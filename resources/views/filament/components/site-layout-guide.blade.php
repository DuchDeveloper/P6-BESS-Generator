{{--
    Site layout guide: the zone → block → group hierarchy, plus a live summary
    of the counts typed into the form ($wire.data). Used by the create wizard
    and the edit form. $architecture / $totalBatteryCount are fallbacks for
    forms that don't carry those fields.
--}}
<div
    x-data="{
        fallbackArch: @js($architecture ?? null),
        fallbackTotal: @js($totalBatteryCount ?? null),
        num(value) {
            const n = parseInt(value, 10);
            return Number.isFinite(n) && n > 0 ? n : 0;
        },
        field(name) { return this.$wire.data?.[name]; },
        get arch() { return this.field('bess_architecture') ?? this.fallbackArch; },
        get containers() { return this.arch === 'integrated_container'; },
        get hasPcs() { return ! this.containers; },
        get hasSut() { return this.arch !== 'no_sut'; },
        get unit() { return this.containers ? 'containers' : 'batteries'; },
        get unitLabel() { return this.containers ? 'Container' : 'Battery'; },
        get zones() { return this.num(this.field('zone_count')); },
        get blocksPerZone() { return this.num(this.field('blocks_per_zone')); },
        get blocks() { return this.zones * this.blocksPerZone; },
        get groupSize() { return this.num(this.field('batteries_per_group')); },
        get groups() { return this.num(this.field('battery_groups_per_block')); },
        get perBlock() { return this.groupSize * this.groups; },
        get total() { return this.blocks * this.perBlock; },
        get pcsGroupSize() { return this.num(this.field('pcs_per_group')); },
        get pcsGroups() { return this.num(this.field('pcs_groups_per_block')); },
        get pcsPerBlock() { return this.pcsGroupSize * this.pcsGroups; },
        get sutsPerBlock() { return this.num(this.field('suts_per_block')); },
        get target() {
            const typed = this.field('total_battery_count');
            return typed === undefined ? this.num(this.fallbackTotal) : this.num(typed);
        },
        get ready() { return this.blocks > 0 && this.perBlock > 0; },
        plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); },
    }"
    class="grid gap-4 lg:grid-cols-2"
>
    {{-- How a site is divided --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">How a site is divided</p>

        {{-- Tree on the left, descriptions in their own column so they wrap. --}}
        <div class="mt-3 grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-1.5 text-xs">
            <div class="whitespace-pre font-mono font-semibold text-gray-950 dark:text-white">Site</div>
            <div></div>

            <div class="whitespace-pre font-mono text-gray-400">└── <span class="font-semibold text-primary-600 dark:text-primary-400">Zone</span></div>
            <div class="text-gray-600 dark:text-gray-400">a large area of the site</div>

            <div class="whitespace-pre font-mono text-gray-400">    └── <span class="font-semibold text-primary-600 dark:text-primary-400">Block</span></div>
            <div class="text-gray-600 dark:text-gray-400">one self-contained work area inside a zone</div>

            <div class="whitespace-pre font-mono text-gray-400">        <span x-text="hasPcs || hasSut ? '├──' : '└──'">├──</span> <span class="font-semibold text-primary-600 dark:text-primary-400" x-text="unitLabel + ' groups'">Battery groups</span></div>
            <div class="text-gray-600 dark:text-gray-400" x-text="unit + ' installed together as one job'">batteries installed together as one job</div>

            <div class="whitespace-pre font-mono text-gray-400" x-show="hasPcs">        <span x-text="hasSut ? '├──' : '└──'">├──</span> <span class="font-semibold text-primary-600 dark:text-primary-400">PCS groups</span></div>
            <div class="text-gray-600 dark:text-gray-400" x-show="hasPcs">PCS units installed together as one job</div>

            <div class="whitespace-pre font-mono text-gray-400" x-show="hasSut">        └── <span class="font-semibold text-primary-600 dark:text-primary-400">SUTs</span></div>
            <div class="text-gray-600 dark:text-gray-400" x-show="hasSut">transformers serving that block</div>
        </div>

        <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
            Power flows
            <span class="font-medium text-gray-800 dark:text-gray-200" x-show="! containers">Battery <span class="text-gray-400">→ DC →</span> PCS</span>
            <span class="font-medium text-gray-800 dark:text-gray-200" x-show="containers">Container (battery + PCS)</span>
            <span x-show="hasSut"><span class="text-gray-400">→ AC →</span> <span class="font-medium text-gray-800 dark:text-gray-200">SUT</span></span>
            <span class="text-gray-400">→ MV →</span> <span class="font-medium text-gray-800 dark:text-gray-200">Switchroom</span>.
            Each group becomes one installation activity in the schedule.
        </p>
    </div>

    {{-- Live summary of the numbers entered --}}
    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10" data-site-summary>
        <p class="text-sm font-semibold text-gray-950 dark:text-white">Your site</p>

        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400" x-show="! ready">
            Enter the zones, blocks and groups below to see what will be built.
        </p>

        <dl class="mt-3 space-y-2 text-sm" x-show="ready" x-cloak>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Blocks</dt>
                <dd class="text-gray-950 dark:text-white">
                    <span class="font-semibold" x-text="blocks"></span>
                    <span class="text-gray-500 dark:text-gray-400" x-text="'(' + plural(zones, 'zone', 'zones') + ' × ' + plural(blocksPerZone, 'block', 'blocks') + ')'"></span>
                </dd>
            </div>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Each block</dt>
                <dd class="text-gray-950 dark:text-white">
                    <span x-text="perBlock + ' ' + unit + ' in ' + plural(groups, 'group', 'groups') + ' of ' + groupSize"></span><span x-show="hasPcs" x-text="' · ' + pcsPerBlock + ' PCS in ' + plural(pcsGroups, 'group', 'groups') + ' of ' + pcsGroupSize"></span><span x-show="hasSut" x-text="' · ' + plural(sutsPerBlock, 'SUT', 'SUTs')"></span>
                </dd>
            </div>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Whole site</dt>
                <dd class="text-gray-950 dark:text-white">
                    <span class="font-semibold" x-text="total + ' ' + unit"></span><span x-show="hasPcs" x-text="' · ' + (blocks * pcsPerBlock) + ' PCS'"></span><span x-show="hasSut" x-text="' · ' + plural(blocks * sutsPerBlock, 'SUT', 'SUTs')"></span>
                </dd>
            </div>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Schedule</dt>
                <dd class="text-gray-950 dark:text-white" x-text="(blocks * groups) + ' ' + unitLabel.toLowerCase() + '-group installation activities, one per group'"></dd>
            </div>
        </dl>

        <div x-show="ready" x-cloak class="mt-4 space-y-2">
            <p class="rounded-lg bg-success-50 px-3 py-2 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-400"
               x-show="target > 0 && target === total">
                ✓ Adds up to the Total <span x-text="unitLabel"></span> Count (<span x-text="target"></span>).
            </p>
            <p class="rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400"
               x-show="target > 0 && target !== total">
                The layout gives <strong x-text="total"></strong> <span x-text="unit"></span> but the Total <span x-text="unitLabel"></span> Count is
                <strong x-text="target"></strong>. Change one of them — validation blocks export until they match.
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400" x-show="! target">
                Enter the Total <span x-text="unitLabel"></span> Count to check the layout adds up.
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400" x-show="hasPcs && pcsGroups > 0 && pcsGroups !== groups">
                <span x-text="unitLabel"></span> groups (<span x-text="groups"></span>) and PCS groups (<span x-text="pcsGroups"></span>) differ.
                Group N is cabled to PCS group N, so check this is intended.
            </p>
        </div>
    </div>
</div>
