<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('wbs_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('wbs_nodes')
                ->nullOnDelete();                          // null = top-level node

            // ── WBS Identity ────────────────────────────────────────────
            $table->string('code');                          // e.g. "1", "1.1", "1.1.1"
            $table->string('name');                          // e.g. "Design", "Civil Design"
            $table->unsignedTinyInteger('level');            // 1=top, 2=discipline, 3=package etc.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // ── WBS Category ────────────────────────────────────────────
            // milestones | management | design | procurement | construction | commissioning
            $table->string('wbs_category')->nullable();

            // ── P6 Export Metadata ──────────────────────────────────────
            $table->string('p6_wbs_id')->nullable();         // P6 PROJWBS.wbs_id
            $table->string('p6_wbs_short_name')->nullable(); // P6 PROJWBS.wbs_short_name

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index(['project_id', 'level']);
            $table->index(['project_id', 'parent_id']);
            $table->unique(['project_id', 'code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('wbs_nodes');
    }
};
