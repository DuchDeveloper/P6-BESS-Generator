<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // BESS Architecture
            $table->string('bess_architecture')->nullable()->after('wbs_profile');

            // Shared cable fields
            $table->string('dc_cable_type')->nullable()->after('bess_architecture');
            $table->string('ac_cable_type')->nullable()->after('dc_cable_type');
            $table->string('mv_cable_type')->nullable()->after('ac_cable_type');

            // Architecture 1 — Integrated Container
            $table->string('oem_product_name')->nullable()->after('mv_cable_type');
            $table->string('container_unit_label')->nullable()->after('oem_product_name');
            $table->unsignedTinyInteger('containers_per_sut')->nullable()->after('container_unit_label');
            $table->boolean('pcs_factory_fitted')->default(true)->after('containers_per_sut');

            // Architecture 2 and 3 — Separate PCS
            $table->string('pcs_unit_type')->nullable()->after('pcs_factory_fitted');
            $table->unsignedTinyInteger('batteries_per_pcs')->nullable()->after('pcs_unit_type');
            $table->unsignedTinyInteger('pcs_per_sut')->nullable()->after('batteries_per_pcs');

            // Architecture 4 — No SUT
            $table->string('pcs_output_voltage')->nullable()->after('pcs_per_sut');

            // Architecture 5 — Cluster
            $table->unsignedTinyInteger('blocks_per_cluster_sut')->nullable()->after('pcs_output_voltage');

            // RMU Configuration
            $table->string('rmu_topology')->default('none')->after('blocks_per_cluster_sut');
            $table->boolean('rmu_on_mv_ring')->default(false)->after('rmu_topology');
            $table->string('rmu_mv_ring_voltage')->nullable()->after('rmu_on_mv_ring');
            $table->string('rmu_unit_type')->nullable()->after('rmu_mv_ring_voltage');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'bess_architecture',
                'dc_cable_type',
                'ac_cable_type',
                'mv_cable_type',
                'oem_product_name',
                'container_unit_label',
                'containers_per_sut',
                'pcs_factory_fitted',
                'pcs_unit_type',
                'batteries_per_pcs',
                'pcs_per_sut',
                'pcs_output_voltage',
                'blocks_per_cluster_sut',
                'rmu_topology',
                'rmu_on_mv_ring',
                'rmu_mv_ring_voltage',
                'rmu_unit_type',
            ]);
        });
    }
};