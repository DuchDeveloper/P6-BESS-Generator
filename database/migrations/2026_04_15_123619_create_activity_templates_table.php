<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('activity_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_template_id')
                ->constrained('package_templates')
                ->cascadeOnDelete();

            // ── Activity Definition ─────────────────────────────────────
            $table->unsignedSmallInteger('sequence');         // Order within package (1, 2, 3...)
            $table->string('name');                           // Activity name
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->boolean('is_milestone')->default(false);  // Zero-duration milestone flag

            // ── Internal Predecessor ────────────────────────────────────
            // Sequence number of the predecessor within this same package
            // null = first activity (depends on package entry anchor only)
            $table->unsignedSmallInteger('predecessor_sequence')->nullable();

            // ── P6 Export Metadata ──────────────────────────────────────
            // task_type: TT_Task | TT_Mile | TT_FinMile | TT_LOE
            $table->string('p6_task_type')->default('TT_Task');

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('package_template_id');
            $table->unique(['package_template_id', 'sequence']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_templates');
    }
};
