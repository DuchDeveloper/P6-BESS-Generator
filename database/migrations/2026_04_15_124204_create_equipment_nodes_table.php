<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('equipment_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // ── Node Type ───────────────────────────────────────────────
            // battery_group | pcs_group | sut | switchroom | control_room |
            // transformer | substation | rtu_panel | ups_dc | hvac | fire_panel
            $table->string('type');

            // ── Topology Position ───────────────────────────────────────
            $table->unsignedTinyInteger('zone_number')->nullable();
            $table->unsignedTinyInteger('block_number')->nullable();
            $table->unsignedTinyInteger('group_number')->nullable();

            // ── Label ───────────────────────────────────────────────────
            // e.g. "Battery Group 1 (Batteries 1-4)"
            // e.g. "PCS Group 3 (PCS 5-6)"
            // e.g. "SUT 1"
            // e.g. "Main Transformer"
            $table->string('label');

            // ── Unit Range ──────────────────────────────────────────────
            // For battery/PCS groups: which units are in this group
            $table->unsignedSmallInteger('unit_from')->nullable(); // e.g. 1
            $table->unsignedSmallInteger('unit_to')->nullable();   // e.g. 4

            // ── Linked Package Instance ─────────────────────────────────
            // The installation package_instance for this node
            $table->foreignId('package_instance_id')
                ->nullable()
                ->constrained('package_instances')
                ->nullOnDelete();

            // ── Commissioning Readiness Output Key ──────────────────────
            // Output key this node produces when ready for commissioning
            $table->string('ready_output_key')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('project_id');
            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'zone_number', 'block_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_nodes');
    }
};
