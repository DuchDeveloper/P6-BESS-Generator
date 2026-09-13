<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('activity_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignId('package_instance_id')
                ->constrained('package_instances')
                ->cascadeOnDelete();
            $table->foreignId('wbs_node_id')
                ->nullable()
                ->constrained('wbs_nodes')
                ->nullOnDelete();

            // ── Activity Identity ───────────────────────────────────────
            $table->string('activity_code');                 // P6-style ID e.g. "S2.1.01", "DES-CIV-001"
            $table->string('name');
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->boolean('is_milestone')->default(false);

            // ── Sequence Within Package ─────────────────────────────────
            $table->unsignedSmallInteger('sequence');

            // ── Schedule Dates (populated after compilation) ────────────
            $table->date('early_start_date')->nullable();
            $table->date('early_finish_date')->nullable();
            $table->date('late_start_date')->nullable();
            $table->date('late_finish_date')->nullable();

            // ── Constraint ──────────────────────────────────────────────
            // none | start_on_or_after | finish_on_or_before | mandatory_start | mandatory_finish
            $table->string('constraint_type')->default('none');
            $table->date('constraint_date')->nullable();

            // ── P6 Export Metadata ──────────────────────────────────────
            $table->string('p6_task_type')->default('TT_Task'); // TT_Task | TT_Mile | TT_FinMile
            $table->string('p6_task_id')->nullable();            // Assigned at export time

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('project_id');
            $table->index('package_instance_id');
            $table->index(['project_id', 'is_milestone']);
            $table->index(['project_id', 'activity_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_instances');
    }
};
