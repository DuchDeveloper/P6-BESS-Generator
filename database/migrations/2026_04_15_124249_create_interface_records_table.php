<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('interface_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // ── Connection ──────────────────────────────────────────────
            $table->foreignId('from_node_id')
                ->constrained('equipment_nodes')
                ->cascadeOnDelete();
            $table->foreignId('to_node_id')
                ->constrained('equipment_nodes')
                ->cascadeOnDelete();

            // ── Interface Type ──────────────────────────────────────────
            // power | control | communications | protection |
            // auxiliary | energisation | commissioning
            $table->string('interface_type');

            // ── Generated Activities ────────────────────────────────────
            $table->boolean('generates_activities')->default(true);

            // Which activities to generate (stored as JSON array of types)
            // e.g. ["cable_install", "cable_termination", "point_to_point", "interface_complete"]
            $table->json('activity_types')->nullable();

            // ── Cable Details ───────────────────────────────────────────
            $table->string('cable_type')->nullable();        // MV | LV | DC | Control | Fibre
            $table->unsignedSmallInteger('cable_count')->nullable();

            // ── Linked WBS Node ─────────────────────────────────────────
            $table->foreignId('wbs_node_id')
                ->nullable()
                ->constrained('wbs_nodes')
                ->nullOnDelete();

            // ── Interface Completion Output Key ─────────────────────────
            $table->string('completion_output_key')->nullable();

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('project_id');
            $table->index(['project_id', 'interface_type']);
            $table->index('from_node_id');
            $table->index('to_node_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('interface_records');
    }
};
