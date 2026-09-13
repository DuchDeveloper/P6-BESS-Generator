<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('energisation_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('energisation_milestone_id')
                ->constrained('energisation_milestones')
                ->cascadeOnDelete();

            // ── Prerequisite Group ──────────────────────────────────────
            // completion | electrical_testing | safety_auxiliaries |
            // controls_communications | approval_authority | network_interface
            $table->string('group_name');
            $table->string('description');

            // ── Resolution ──────────────────────────────────────────────
            // The output_key that must be resolved to satisfy this prerequisite
            $table->string('required_output_key');

            // ── Status ──────────────────────────────────────────────────
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by_activity_id')
                ->nullable()
                ->constrained('activity_instances')
                ->nullOnDelete();

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('energisation_milestone_id');
            $table->index('required_output_key');
        });
    }

    public function down()
    {
        Schema::dropIfExists('energisation_prerequisites');
    }
};
