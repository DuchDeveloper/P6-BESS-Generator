<div
    x-data="architectureSelector()"
    x-init="
        selected        = $wire.data.bess_architecture ?? null;
        rmuSelected     = $wire.data.rmu_topology      ?? 'none';
        fields.rmu_topology         = rmuSelected;
        fields.rmu_on_mv_ring       = $wire.data.rmu_on_mv_ring       ?? false;
        fields.rmu_mv_ring_voltage  = $wire.data.rmu_mv_ring_voltage  ?? '';
        fields.rmu_unit_type        = $wire.data.rmu_unit_type        ?? '';
        fields.dc_cable_type        = $wire.data.dc_cable_type        ?? '';
        fields.ac_cable_type        = $wire.data.ac_cable_type        ?? '';
        fields.mv_cable_type        = $wire.data.mv_cable_type        ?? '';
        fields.oem_product_name     = $wire.data.oem_product_name     ?? '';
        fields.container_unit_label = $wire.data.container_unit_label ?? 'Battery Container';
        fields.containers_per_sut   = $wire.data.containers_per_sut   ?? 4;
        fields.pcs_factory_fitted   = $wire.data.pcs_factory_fitted   ?? true;
        fields.pcs_unit_type        = $wire.data.pcs_unit_type        ?? '';
        fields.batteries_per_pcs    = $wire.data.batteries_per_pcs    ?? null;
        fields.pcs_per_sut          = $wire.data.pcs_per_sut          ?? null;
        fields.pcs_output_voltage   = $wire.data.pcs_output_voltage   ?? '';
        fields.blocks_per_cluster_sut = $wire.data.blocks_per_cluster_sut ?? 2;
        if (selected === 'no_sut') {
            rmuDisabled = true;
            rmuDisabledReason = 'RMU is not applicable for Architecture 4. There is no SUT in this configuration.';
        }
    "
    class="space-y-8"
>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 1 — BESS ARCHITECTURE                                 --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                BESS Equipment Architecture
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Select the architecture that matches your project configuration.
                Only one architecture can be active at a time.
            </p>
        </div>

        <div class="space-y-3">
            <template x-for="arch in architectures" :key="arch.key">
                <div
                    class="rounded-xl border-2 transition-all duration-200"
                    :class="isArchSelected(arch.key)
                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/20 shadow-md'
                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300'"
                >
                    {{-- Card Header --}}
                    <div
                        class="flex items-start gap-4 p-5 cursor-pointer select-none"
                        @click="selectArchitecture(arch.key)"
                    >
                        <div class="mt-0.5 flex-shrink-0">
                            <div
                                class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors"
                                :class="isArchSelected(arch.key)
                                    ? 'border-primary-500 bg-primary-500'
                                    : 'border-gray-300 dark:border-gray-600'"
                            >
                                <svg x-show="isArchSelected(arch.key)" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 12 12">
                                    <path d="M3.707 5.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4a1 1 0 00-1.414-1.414L5 6.586 3.707 5.293z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span
                                class="font-semibold text-sm"
                                :class="isArchSelected(arch.key) ? 'text-primary-700 dark:text-primary-400' : 'text-gray-900 dark:text-white'"
                                x-text="arch.label"
                            ></span>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-text="arch.subtitle"></p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 font-medium" x-text="arch.oems"></p>
                        </div>
                    </div>

                    {{-- Expanded Fields --}}
                    <div x-show="isArchSelected(arch.key)" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                        <div class="p-5 space-y-5">

                            {{-- Diagram --}}
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Connection Diagram</p>
                                <pre class="text-xs text-gray-600 dark:text-gray-300 font-mono whitespace-pre leading-relaxed" x-text="arch.diagram"></pre>
                            </div>

                            {{-- ── Architecture 1 Fields ──────────── --}}
                            <template x-if="arch.key === 'integrated_container'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">OEM / Product Name <span class="text-gray-400 font-normal">(optional)</span></label>
                                        <input type="text" x-model="fields.oem_product_name" @change="$wire.set('data.oem_product_name', fields.oem_product_name)" placeholder="e.g. Tesla Megapack 2 XL" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Container Unit Label <span class="text-red-500">*</span></label>
                                        <select x-model="fields.container_unit_label" @change="$wire.set('data.container_unit_label', fields.container_unit_label)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="Battery Container">Battery Container</option>
                                            <option value="BESS Unit">BESS Unit</option>
                                            <option value="Megapack">Megapack</option>
                                            <option value="Battery Cabinet">Battery Cabinet</option>
                                            <option value="Battery Module">Battery Module</option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-400">Used in all activity names for this project</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Containers per SUT <span class="text-red-500">*</span></label>
                                        <input type="number" x-model.number="fields.containers_per_sut" @change="$wire.set('data.containers_per_sut', fields.containers_per_sut)" min="1" max="20" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AC Cable Type &mdash; Container to SUT <span class="text-red-500">*</span></label>
                                        <select x-model="fields.ac_cable_type" @change="$wire.set('data.ac_cable_type', fields.ac_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select cable type</option>
                                            <option value="LV AC">LV AC</option>
                                            <option value="MV AC">MV AC</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MV Cable Type &mdash; SUT to Switchroom <span class="text-red-500">*</span></label>
                                        <select x-model="fields.mv_cable_type" @change="$wire.set('data.mv_cable_type', fields.mv_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select voltage</option>
                                            <option value="MV 11kV">MV 11kV</option>
                                            <option value="MV 22kV">MV 22kV</option>
                                            <option value="MV 33kV">MV 33kV</option>
                                            <option value="MV 66kV">MV 66kV</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">PCS Scope</label>
                                        <div class="flex flex-col gap-2">
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input type="radio" x-model="fields.pcs_factory_fitted" :value="true" @change="$wire.set('data.pcs_factory_fitted', true)" class="mt-0.5 text-primary-500"/>
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Factory-fitted &mdash; no site installation required</span>
                                                    <p class="text-xs text-gray-400">PCS pre-assembled at factory. No PCS site activities generated.</p>
                                                </div>
                                            </label>
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input type="radio" x-model="fields.pcs_factory_fitted" :value="false" @change="$wire.set('data.pcs_factory_fitted', false)" class="mt-0.5 text-primary-500"/>
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Partial site assembly required</span>
                                                    <p class="text-xs text-gray-400">Partial PCS installation activities will be generated.</p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- ── Architecture 2 Fields ──────────── --}}
                            <template x-if="arch.key === 'separate_pcs_one_per_sut'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Batteries per PCS <span class="text-red-500">*</span></label>
                                        <input type="number" x-model.number="fields.batteries_per_pcs" @change="$wire.set('data.batteries_per_pcs', fields.batteries_per_pcs)" min="1" max="20" placeholder="e.g. 3" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">PCS per SUT</label>
                                        <input type="number" value="1" disabled class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 px-3 py-2 text-sm text-gray-400 cursor-not-allowed"/>
                                        <p class="mt-1 text-xs text-gray-400">Fixed at 1 for this architecture</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">PCS Unit Type <span class="text-red-500">*</span></label>
                                        <select x-model="fields.pcs_unit_type" @change="$wire.set('data.pcs_unit_type', fields.pcs_unit_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select PCS type</option>
                                            <option value="String Inverter Skid">String Inverter Skid</option>
                                            <option value="Central Inverter">Central Inverter</option>
                                            <option value="Modular PCS Skid">Modular PCS Skid</option>
                                            <option value="Containerised PCS">Containerised PCS</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">DC Cable Type &mdash; Battery to PCS <span class="text-red-500">*</span></label>
                                        <select x-model="fields.dc_cable_type" @change="$wire.set('data.dc_cable_type', fields.dc_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select cable type</option>
                                            <option value="LV DC">LV DC</option>
                                            <option value="HV DC">HV DC</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AC Cable Type &mdash; PCS to SUT <span class="text-red-500">*</span></label>
                                        <select x-model="fields.ac_cable_type" @change="$wire.set('data.ac_cable_type', fields.ac_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select cable type</option>
                                            <option value="LV AC">LV AC</option>
                                            <option value="MV AC">MV AC</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MV Cable Type &mdash; SUT to Switchroom <span class="text-red-500">*</span></label>
                                        <select x-model="fields.mv_cable_type" @change="$wire.set('data.mv_cable_type', fields.mv_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select voltage</option>
                                            <option value="MV 11kV">MV 11kV</option>
                                            <option value="MV 22kV">MV 22kV</option>
                                            <option value="MV 33kV">MV 33kV</option>
                                            <option value="MV 66kV">MV 66kV</option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            {{-- ── Architecture 3 Fields ──────────── --}}
                            <template x-if="arch.key === 'separate_pcs_multi_per_sut'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Batteries per PCS <span class="text-red-500">*</span></label>
                                        <input type="number" x-model.number="fields.batteries_per_pcs" @change="$wire.set('data.batteries_per_pcs', fields.batteries_per_pcs)" min="1" max="20" placeholder="e.g. 3" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">PCS per SUT <span class="text-red-500">*</span></label>
                                        <input type="number" x-model.number="fields.pcs_per_sut" @change="$wire.set('data.pcs_per_sut', fields.pcs_per_sut)" min="2" max="10" placeholder="e.g. 2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                                        <p class="mt-1 text-xs text-gray-400">Minimum 2 for this architecture</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">PCS Unit Type <span class="text-red-500">*</span></label>
                                        <select x-model="fields.pcs_unit_type" @change="$wire.set('data.pcs_unit_type', fields.pcs_unit_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select PCS type</option>
                                            <option value="String Inverter Skid">String Inverter Skid</option>
                                            <option value="Central Inverter">Central Inverter</option>
                                            <option value="Modular PCS Skid">Modular PCS Skid</option>
                                            <option value="Containerised PCS">Containerised PCS</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">DC Cable Type &mdash; Battery to PCS <span class="text-red-500">*</span></label>
                                        <select x-model="fields.dc_cable_type" @change="$wire.set('data.dc_cable_type', fields.dc_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select cable type</option>
                                            <option value="LV DC">LV DC</option>
                                            <option value="HV DC">HV DC</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AC Cable Type &mdash; PCS to SUT <span class="text-red-500">*</span></label>
                                        <select x-model="fields.ac_cable_type" @change="$wire.set('data.ac_cable_type', fields.ac_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select cable type</option>
                                            <option value="LV AC">LV AC</option>
                                            <option value="MV AC">MV AC</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MV Cable Type &mdash; SUT to Switchroom <span class="text-red-500">*</span></label>
                                        <select x-model="fields.mv_cable_type" @change="$wire.set('data.mv_cable_type', fields.mv_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select voltage</option>
                                            <option value="MV 11kV">MV 11kV</option>
                                            <option value="MV 22kV">MV 22kV</option>
                                            <option value="MV 33kV">MV 33kV</option>
                                            <option value="MV 66kV">MV 66kV</option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            {{-- ── Architecture 4 Fields ──────────── --}}
                            <template x-if="arch.key === 'no_sut'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">PCS Output Voltage <span class="text-red-500">*</span></label>
                                        <select x-model="fields.pcs_output_voltage" @change="$wire.set('data.pcs_output_voltage', fields.pcs_output_voltage)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select output voltage</option>
                                            <option value="11kV">11kV</option>
                                            <option value="22kV">22kV</option>
                                            <option value="33kV">33kV</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">DC Cable Type &mdash; Battery to PCS <span class="text-red-500">*</span></label>
                                        <select x-model="fields.dc_cable_type" @change="$wire.set('data.dc_cable_type', fields.dc_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select cable type</option>
                                            <option value="LV DC">LV DC</option>
                                            <option value="HV DC">HV DC</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MV Cable Type &mdash; PCS to Switchboard <span class="text-red-500">*</span></label>
                                        <select x-model="fields.mv_cable_type" @change="$wire.set('data.mv_cable_type', fields.mv_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select voltage</option>
                                            <option value="MV 11kV">MV 11kV</option>
                                            <option value="MV 22kV">MV 22kV</option>
                                            <option value="MV 33kV">MV 33kV</option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            {{-- ── Architecture 5 Fields ──────────── --}}
                            <template x-if="arch.key === 'cluster'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Blocks per Shared SUT <span class="text-red-500">*</span></label>
                                        <input type="number" x-model.number="fields.blocks_per_cluster_sut" @change="$wire.set('data.blocks_per_cluster_sut', fields.blocks_per_cluster_sut)" min="2" max="10" placeholder="e.g. 2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500"/>
                                        <p class="mt-1 text-xs text-gray-400">Minimum 2 blocks per shared SUT</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AC Cable Type &mdash; PCS to SUT <span class="text-red-500">*</span></label>
                                        <select x-model="fields.ac_cable_type" @change="$wire.set('data.ac_cable_type', fields.ac_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select cable type</option>
                                            <option value="LV AC">LV AC</option>
                                            <option value="MV AC">MV AC</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MV Cable Type &mdash; SUT to Switchroom <span class="text-red-500">*</span></label>
                                        <select x-model="fields.mv_cable_type" @change="$wire.set('data.mv_cable_type', fields.mv_cable_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                            <option value="">Select voltage</option>
                                            <option value="MV 11kV">MV 11kV</option>
                                            <option value="MV 22kV">MV 22kV</option>
                                            <option value="MV 33kV">MV 33kV</option>
                                            <option value="MV 66kV">MV 66kV</option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            {{-- Info Banner --}}
                            <div class="rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 p-4">
                                <p class="text-xs font-semibold text-blue-700 dark:text-blue-400 uppercase tracking-wide mb-2">What will be generated</p>
                                <ul class="space-y-1">
                                    <template x-for="item in arch.infoGenerated">
                                        <li class="flex items-start gap-2 text-sm text-blue-700 dark:text-blue-300">
                                            <span class="text-blue-500 mt-0.5">&#10003;</span>
                                            <span x-text="item"></span>
                                        </li>
                                    </template>
                                </ul>
                                <template x-if="arch.infoSuppressed.length > 0">
                                    <div class="mt-3 pt-3 border-t border-blue-200 dark:border-blue-800">
                                        <p class="text-xs font-semibold text-blue-400 uppercase tracking-wide mb-2">What will be suppressed</p>
                                        <ul class="space-y-1">
                                            <template x-for="item in arch.infoSuppressed">
                                                <li class="flex items-start gap-2 text-sm text-blue-400">
                                                    <span class="mt-0.5">&ndash;</span>
                                                    <span x-text="item"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </div>

                            {{-- Warning Banner --}}
                            <template x-if="arch.warningText">
                                <div class="rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 p-4">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495z" clip-rule="evenodd"/>
                                        </svg>
                                        <p class="text-sm text-amber-700 dark:text-amber-400" x-text="arch.warningText"></p>
                                    </div>
                                </div>
                            </template>

                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 2 — RMU CONFIGURATION                                 --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="mb-4">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    RMU Configuration
                </h2>
                <span
                    x-show="rmuDisabled"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
                >
                    Not applicable
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"
               x-show="!rmuDisabled">
                How are your SUT outputs connected to the MV network?
                Select the RMU topology that matches your project.
            </p>
            <p class="mt-1 text-sm text-amber-600 dark:text-amber-400"
               x-show="rmuDisabled"
               x-text="rmuDisabledReason">
            </p>
        </div>

        <div :class="rmuDisabled ? 'opacity-40 pointer-events-none select-none' : ''" class="space-y-3">
            <template x-for="rmu in rmuTopologies" :key="rmu.key">
                <div
                    class="rounded-xl border-2 transition-all duration-200"
                    :class="isRmuSelected(rmu.key)
                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/20 shadow-md'
                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300'"
                >
                    <div
                        class="flex items-start gap-4 p-5 cursor-pointer select-none"
                        @click="selectRmu(rmu.key)"
                    >
                        <div class="mt-0.5 flex-shrink-0">
                            <div
                                class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors"
                                :class="isRmuSelected(rmu.key)
                                    ? 'border-primary-500 bg-primary-500'
                                    : 'border-gray-300 dark:border-gray-600'"
                            >
                                <svg x-show="isRmuSelected(rmu.key)" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 12 12">
                                    <path d="M3.707 5.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4a1 1 0 00-1.414-1.414L5 6.586 3.707 5.293z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span
                                class="font-semibold text-sm"
                                :class="isRmuSelected(rmu.key) ? 'text-primary-700 dark:text-primary-400' : 'text-gray-900 dark:text-white'"
                                x-text="rmu.label"
                            ></span>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-text="rmu.subtitle"></p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 italic" x-text="rmu.typicalSize"></p>
                        </div>
                    </div>

                    <div x-show="isRmuSelected(rmu.key) && rmu.key !== 'none'" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                        <div class="p-5 space-y-5">

                            <div class="rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Connection Diagram</p>
                                <pre class="text-xs text-gray-600 dark:text-gray-300 font-mono whitespace-pre leading-relaxed" x-text="rmu.diagram"></pre>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        MV Connection Type <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex flex-col gap-2">
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input type="radio" x-model="fields.rmu_on_mv_ring" :value="false" @change="$wire.set('data.rmu_on_mv_ring', false)" class="mt-0.5 text-primary-500"/>
                                            <div>
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Radial</span>
                                                <p class="text-xs text-gray-400">Each RMU connects back to the switchroom independently.</p>
                                            </div>
                                        </label>
                                        <label class="flex items-start gap-3 cursor-pointer" x-show="rmu.showRingOption">
                                            <input type="radio" x-model="fields.rmu_on_mv_ring" :value="true" @change="$wire.set('data.rmu_on_mv_ring', true)" class="mt-0.5 text-primary-500"/>
                                            <div>
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Ring</span>
                                                <p class="text-xs text-gray-400">RMUs are daisy-chained in an MV ring. Ring sequencing logic will be applied.</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div x-show="fields.rmu_on_mv_ring">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MV Ring Voltage <span class="text-red-500">*</span></label>
                                    <select x-model="fields.rmu_mv_ring_voltage" @change="$wire.set('data.rmu_mv_ring_voltage', fields.rmu_mv_ring_voltage)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                        <option value="">Select ring voltage</option>
                                        <option value="11kV">11kV</option>
                                        <option value="22kV">22kV</option>
                                        <option value="33kV">33kV</option>
                                        <option value="66kV">66kV</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">RMU Unit Type <span class="text-red-500">*</span></label>
                                    <select x-model="fields.rmu_unit_type" @change="$wire.set('data.rmu_unit_type', fields.rmu_unit_type)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                        <option value="">Select RMU type</option>
                                        <option value="Outdoor Compact RMU">Outdoor Compact RMU</option>
                                        <option value="Indoor RMU">Indoor RMU</option>
                                        <option value="Gas-Insulated RMU">Gas-Insulated RMU (SF6 / Clean Air)</option>
                                        <option value="Air-Insulated RMU">Air-Insulated RMU</option>
                                        <option value="Solid-Insulated RMU">Solid-Insulated RMU</option>
                                    </select>
                                </div>
                            </div>

                            <div class="rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 p-4">
                                <p class="text-xs font-semibold text-blue-700 dark:text-blue-400 uppercase tracking-wide mb-2">What will be generated</p>
                                <ul class="space-y-1">
                                    <template x-for="item in rmu.infoGenerated">
                                        <li class="flex items-start gap-2 text-sm text-blue-700 dark:text-blue-300">
                                            <span class="text-blue-500 mt-0.5">&#10003;</span>
                                            <span x-text="item"></span>
                                        </li>
                                    </template>
                                </ul>
                                <template x-if="rmu.infoSuppressed.length > 0">
                                    <div class="mt-3 pt-3 border-t border-blue-200 dark:border-blue-800">
                                        <p class="text-xs font-semibold text-blue-400 uppercase tracking-wide mb-2">What will be suppressed</p>
                                        <ul class="space-y-1">
                                            <template x-for="item in rmu.infoSuppressed">
                                                <li class="flex items-start gap-2 text-sm text-blue-400">
                                                    <span>&ndash;</span>
                                                    <span x-text="item"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                                <div x-show="fields.rmu_on_mv_ring" class="mt-3 pt-3 border-t border-blue-200 dark:border-blue-800">
                                    <p class="text-sm text-blue-600 dark:text-blue-400">
                                        &#8635; Ring sequencing logic will be applied. MV ring cable pulls will be
                                        scheduled in ring order.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Confirmation Modal ──────────────────────────────────────── --}}
    <div
        x-show="showConfirmModal"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="cancelChange()"
    >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Change architecture?</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Changing the architecture will clear all fields you have already filled in.
                        This cannot be undone.
                    </p>
                </div>
            </div>
            <div class="mt-5 flex gap-3 justify-end">
                <button @click="cancelChange()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button @click="confirmChange()" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors">Yes, change architecture</button>
            </div>
        </div>
    </div>

</div>

{{-- Alpine.js data component — registered inline for Filament compatibility --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('architectureSelector', () => ({
        selected: null,
        previous: null,
        showConfirmModal: false,
        pendingSelection: null,

        rmuSelected: 'none',
        rmuDisabled: false,
        rmuDisabledReason: '',

        fields: {
            dc_cable_type: '',
            ac_cable_type: '',
            mv_cable_type: '',
            oem_product_name: '',
            container_unit_label: 'Battery Container',
            containers_per_sut: 4,
            pcs_factory_fitted: true,
            pcs_unit_type: '',
            batteries_per_pcs: null,
            pcs_per_sut: null,
            pcs_output_voltage: '',
            blocks_per_cluster_sut: 2,
            rmu_topology: 'none',
            rmu_on_mv_ring: false,
            rmu_mv_ring_voltage: '',
            rmu_unit_type: '',
        },

        architectures: [
            {
                key: 'integrated_container',
                label: 'Architecture 1 \u2014 Integrated Container',
                subtitle: 'Battery and PCS are factory-assembled in one container. No separate PCS installation on site.',
                oems: 'Tesla Megapack \u00b7 Sungrow ST \u00b7 BYD MC-Cube \u00b7 CATL EnerC \u00b7 Gotion',
                diagram: 'Container 1 (Battery + PCS) \u2500\u2500\u2510\nContainer 2 (Battery + PCS) \u2500\u2500\u253c\u2500\u2500\u2192 SUT \u2500\u2500\u2192 RMU / Switchroom\nContainer N (Battery + PCS) \u2500\u2500\u2518',
                infoGenerated: [
                    'Container installation activities per group',
                    'AC cable installation from container to SUT',
                    'SUT installation and MV cable to switchroom or RMU',
                ],
                infoSuppressed: [
                    'Separate PCS installation activities \u2014 not applicable',
                    'DC cable installation between battery and PCS \u2014 factory-fitted',
                ],
                warningText: null,
                disablesRmu: false,
            },
            {
                key: 'separate_pcs_one_per_sut',
                label: 'Architecture 2 \u2014 Separate Battery and PCS, One PCS per SUT',
                subtitle: 'Battery strings connect to a separate PCS skid. Each PCS has its own dedicated SUT.',
                oems: 'Fluence \u00b7 W\u00e4rtsil\u00e4 \u00b7 Powin \u2014 older configurations',
                diagram: 'Battery 1 \u2500\u2500\u2510\nBattery 2 \u2500\u2500\u253c\u2500\u2500\u2192 PCS 1 \u2500\u2500\u2192 SUT 1 \u2500\u2500\u2192 RMU / Switchroom\nBattery N \u2500\u2500\u2518',
                infoGenerated: [
                    'Battery group installation activities',
                    'PCS installation activities (one per SUT)',
                    'DC cable installation from battery to PCS',
                    'AC cable installation from PCS to SUT',
                    'SUT installation and MV cable to switchroom or RMU',
                ],
                infoSuppressed: [],
                warningText: null,
                disablesRmu: false,
            },
            {
                key: 'separate_pcs_multi_per_sut',
                label: 'Architecture 3 \u2014 Separate Battery and PCS, Multiple PCS per SUT',
                subtitle: 'Battery strings connect to a separate PCS skid. Multiple PCS units share one SUT.',
                oems: 'Fluence Gridstack \u00b7 W\u00e4rtsil\u00e4 Gridsolv \u00b7 Powin Stack \u00b7 Samsung SDI',
                diagram: 'Battery 1-3 \u2500\u2500\u2192 PCS 1 \u2500\u2500\u2510\n                        \u251c\u2500\u2500\u2192 SUT 1 \u2500\u2500\u2192 RMU / Switchroom\nBattery 4-6 \u2500\u2500\u2192 PCS 2 \u2500\u2500\u2518',
                infoGenerated: [
                    'Battery group installation activities per group',
                    'PCS installation activities per PCS group',
                    'DC cable installation from battery groups to PCS',
                    'AC cable installation from PCS groups to SUT',
                    'SUT installation and MV cable to switchroom or RMU',
                ],
                infoSuppressed: [],
                warningText: null,
                disablesRmu: false,
            },
            {
                key: 'no_sut',
                label: 'Architecture 4 \u2014 No SUT (Direct MV PCS Output)',
                subtitle: 'PCS output is MV-rated. No step-up transformer between PCS and switchboard.',
                oems: 'Some ABB \u00b7 Schneider Electric \u00b7 Ingeteam configurations',
                diagram: 'Battery 1 \u2500\u2500\u2192 PCS 1 (MV output) \u2500\u2500\u2192 MV Switchboard\nBattery 2 \u2500\u2500\u2192 PCS 2 (MV output) \u2500\u2500\u2192 MV Switchboard',
                infoGenerated: [
                    'Battery group installation activities',
                    'PCS installation activities',
                    'DC cable installation from battery to PCS',
                    'MV cable installation from PCS directly to MV switchboard',
                ],
                infoSuppressed: [
                    'SUT installation activities \u2014 not applicable',
                    'LV AC cable between PCS and SUT \u2014 not applicable',
                    'RMU configuration \u2014 not applicable without a SUT',
                ],
                warningText: 'SUT and RMU are not applicable for this architecture. The RMU configuration section below will be disabled.',
                disablesRmu: true,
            },
            {
                key: 'cluster',
                label: 'Architecture 5 \u2014 Cluster (Multiple Blocks share one SUT)',
                subtitle: 'PCS outputs from multiple blocks connect to a single shared SUT. Common on large projects above 200MW.',
                oems: 'Large utility projects \u2014 any OEM \u2014 where SUT count is minimised',
                diagram: 'Block 1: PCS \u2500\u2500\u2510\n               \u251c\u2500\u2500\u2192 Shared SUT \u2500\u2500\u2192 RMU / Switchroom\nBlock 2: PCS \u2500\u2500\u2518',
                infoGenerated: [
                    'Battery and PCS installation activities per block',
                    'DC and AC cable activities per group',
                    'One SUT per cluster (not per block)',
                    'Cross-block predecessor logic at SUT level',
                ],
                infoSuppressed: [],
                warningText: 'SUT installation is generated once per cluster. Delays in any block within the cluster will affect the shared SUT start date.',
                disablesRmu: false,
            },
        ],

        rmuTopologies: [
            {
                key: 'none',
                label: 'No RMU',
                subtitle: 'SUT connects directly to the switchroom MV busbar.',
                typicalSize: 'Typical for projects below 50MW or where SUT count is low.',
                diagram: 'SUT 1 \u2500\u2500\u2500\u2500\u2500\u2192 Switchroom MV Busbar\nSUT 2 \u2500\u2500\u2500\u2500\u2500\u2192 Switchroom MV Busbar',
                infoGenerated: ['MV cable installation directly from SUT to switchroom', 'MV cable termination at SUT and switchroom'],
                infoSuppressed: ['No RMU civil works, installation, or testing activities'],
                showRingOption: false,
            },
            {
                key: 'per_sut',
                label: 'RMU per SUT',
                subtitle: 'One RMU is installed at each SUT output. RMUs connect back to the switchroom on a radial or ring feeder.',
                typicalSize: 'Typical for 50MW\u2013200MW projects.',
                diagram: 'SUT 1 \u2500\u2500\u2192 RMU 1 \u2500\u2500\u2510\nSUT 2 \u2500\u2500\u2192 RMU 2 \u2500\u2500\u253c\u2500\u2500\u2192 Switchroom\nSUT N \u2500\u2500\u2192 RMU N \u2500\u2500\u2518',
                infoGenerated: ['One RMU node per SUT', 'Civil, mechanical, electrical and testing activities per RMU', 'MV cable from SUT to RMU and from RMU to switchroom', 'RMU control and protection cabling and testing'],
                infoSuppressed: [],
                showRingOption: true,
            },
            {
                key: 'per_block',
                label: 'RMU per Block',
                subtitle: 'One collector RMU per block aggregates all SUT outputs within that block.',
                typicalSize: 'Typical for 100MW\u2013500MW projects.',
                diagram: 'Block 1: SUT 1, SUT 2 \u2500\u2500\u2192 Block RMU 1 \u2500\u2500\u2510\n                                         \u251c\u2500\u2500\u2192 Switchroom\nBlock 2: SUT 3, SUT 4 \u2500\u2500\u2192 Block RMU 2 \u2500\u2500\u2518',
                infoGenerated: ['One block collector RMU node per block', 'Civil, mechanical, electrical and testing activities per RMU', 'MV cables from all SUTs in block to block RMU', 'MV cable from block RMU to switchroom'],
                infoSuppressed: [],
                showRingOption: true,
            },
            {
                key: 'per_zone',
                label: 'RMU per Zone',
                subtitle: 'One collector RMU per zone aggregates all block or SUT outputs before connecting to main transformer or substation.',
                typicalSize: 'Typical for projects above 200MW.',
                diagram: 'Zone 1 blocks \u2500\u2500\u2192 Zone RMU 1 \u2500\u2500\u2510\n                               \u251c\u2500\u2500\u2192 Main Transformer\nZone 2 blocks \u2500\u2500\u2192 Zone RMU 2 \u2500\u2500\u2518',
                infoGenerated: ['One zone collector RMU node per zone', 'Civil, mechanical, electrical and testing activities per RMU', 'MV cables from all zone sources to zone RMU', 'MV cable from zone RMU to main transformer or substation'],
                infoSuppressed: [],
                showRingOption: true,
            },
            {
                key: 'per_sut_and_block',
                label: 'RMU per SUT and per Block (Two-Level)',
                subtitle: 'SUT-level RMUs feed into a block collector RMU. Two levels of RMU infrastructure.',
                typicalSize: 'Typical for large projects above 300MW requiring staged isolation.',
                diagram: 'SUT 1 \u2500\u2500\u2192 RMU 1 \u2500\u2500\u2510\n                   \u251c\u2500\u2500\u2192 Block RMU \u2500\u2500\u2192 Switchroom\nSUT 2 \u2500\u2500\u2192 RMU 2 \u2500\u2500\u2518',
                infoGenerated: ['SUT-level RMU nodes \u2014 one per SUT', 'Block collector RMU nodes \u2014 one per block', 'Full civil, mechanical, electrical and testing activities at both levels', 'MV cables from SUT to SUT RMU and from SUT RMU to block collector RMU'],
                infoSuppressed: [],
                showRingOption: true,
            },
        ],

        selectArchitecture(key) {
            if (this.selected === key) return;
            if (this.selected !== null && this.hasPopulatedFields()) {
                this.pendingSelection = key;
                this.showConfirmModal = true;
                return;
            }
            this.applyArchitectureSelection(key);
        },

        applyArchitectureSelection(key) {
            this.previous = this.selected;
            this.selected = key;
            const arch = this.architectures.find(a => a.key === key);
            if (arch && arch.disablesRmu) {
                this.rmuDisabled = true;
                this.rmuDisabledReason = 'RMU is not applicable for Architecture 4. There is no SUT in this configuration.';
                this.rmuSelected = 'none';
                this.fields.rmu_topology = 'none';
                this.$wire.set('data.rmu_topology', 'none');
            } else {
                this.rmuDisabled = false;
                this.rmuDisabledReason = '';
            }
            this.$wire.set('data.bess_architecture', key);
        },

        confirmChange() {
            this.clearArchitectureFields();
            this.applyArchitectureSelection(this.pendingSelection);
            this.showConfirmModal = false;
            this.pendingSelection = null;
        },

        cancelChange() {
            this.showConfirmModal = false;
            this.pendingSelection = null;
        },

        clearArchitectureFields() {
            this.fields.dc_cable_type = '';
            this.fields.ac_cable_type = '';
            this.fields.mv_cable_type = '';
            this.fields.oem_product_name = '';
            this.fields.container_unit_label = 'Battery Container';
            this.fields.containers_per_sut = 4;
            this.fields.pcs_factory_fitted = true;
            this.fields.pcs_unit_type = '';
            this.fields.batteries_per_pcs = null;
            this.fields.pcs_per_sut = null;
            this.fields.pcs_output_voltage = '';
            this.fields.blocks_per_cluster_sut = 2;
        },

        hasPopulatedFields() {
            return this.fields.batteries_per_pcs !== null
                || this.fields.pcs_per_sut !== null
                || this.fields.containers_per_sut !== 4
                || this.fields.dc_cable_type !== ''
                || this.fields.oem_product_name !== '';
        },

        isArchSelected(key) { return this.selected === key; },

        selectRmu(key) {
            if (this.rmuDisabled) return;
            if (this.rmuSelected === key) return;
            this.rmuSelected = key;
            this.fields.rmu_topology = key;
            this.$wire.set('data.rmu_topology', key);
            if (key === 'none') {
                this.fields.rmu_on_mv_ring = false;
                this.fields.rmu_mv_ring_voltage = '';
                this.fields.rmu_unit_type = '';
            }
        },

        isRmuSelected(key) { return this.rmuSelected === key; },

        get showRingOption() {
            const rmu = this.rmuTopologies.find(r => r.key === this.rmuSelected);
            return rmu ? rmu.showRingOption : false;
        },
    }));
});
</script>