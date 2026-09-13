<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                  ->constrained('projects')
                  ->cascadeOnDelete();
            $table->foreignId('zone_configuration_id')
                  ->constrained('zone_configurations')
                  ->cascadeOnDelete();

            // ── Block Identity ───────────────────────────────────────────
            $table->unsignedTinyInteger('zone_number');
            $table->unsignedTinyInteger('block_number');      // 1, 2, 3... within zone
            $table->string('block_label')->nullable();         // Custom name e.g. "Block A"

            // ── Battery Configuration (user-entered) ─────────────────────
            $table->unsignedSmallInteger('battery_count');    // Batteries in this block
            $table->unsignedTinyInteger('battery_group_size');// Group size for batteries
                                                               // (overrides project default)

            // ── PCS Configuration (user-entered) ─────────────────────────
            $table->unsignedSmallInteger('pcs_count')->default(0);
            $table->unsignedTinyInteger('pcs_group_size')->default(2);

            // ── SUT Configuration (user-entered) ─────────────────────────
            $table->unsignedTinyInteger('sut_count')->default(0);

            // ── Calculated — Batteries ───────────────────────────────────
            // Populated by application when user saves block config
            $table->unsignedTinyInteger('full_battery_group_count')->default(0);
                  // floor(battery_count / battery_group_size)
            $table->unsignedTinyInteger('battery_remainder')->default(0);
                  // battery_count MOD battery_group_size
            $table->boolean('has_battery_partial_group')->default(false);

            // ── Calculated — PCS ─────────────────────────────────────────
            $table->unsignedTinyInteger('full_pcs_group_count')->default(0);
            $table->unsignedTinyInteger('pcs_remainder')->default(0);
            $table->boolean('has_pcs_partial_group')->default(false);

            // ── Remainder Decisions ──────────────────────────────────────
            // pending | accepted | group_size_changed | battery_count_changed
            $table->string('battery_remainder_decision')->default('pending');
            $table->string('pcs_remainder_decision')->default('pending');

            // ── Compilation Gate ─────────────────────────────────────────
            // true = all remainders resolved, block is ready for compilation
            $table->boolean('is_configured')->default(false);

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->unique(['project_id', 'zone_number', 'block_number']);
            $table->index('project_id');
            $table->index('zone_configuration_id');
            $table->index(['project_id', 'is_configured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_configurations');
    }
};
