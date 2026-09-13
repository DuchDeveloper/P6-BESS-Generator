<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                  ->constrained('projects')
                  ->cascadeOnDelete();

            // ── Zone Identity ────────────────────────────────────────────
            $table->unsignedTinyInteger('zone_number');      // 1, 2, 3...
            $table->string('zone_label')->nullable();         // Custom name e.g. "Zone North"

            // ── Zone Configuration ───────────────────────────────────────
            $table->unsignedTinyInteger('block_count');       // How many blocks in this zone

            // ── Calculated (populated after block configs are saved) ─────
            $table->unsignedSmallInteger('total_batteries')->default(0); // Sum of all block battery counts
            $table->unsignedSmallInteger('total_groups')->default(0);    // Sum of all groups in zone

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->unique(['project_id', 'zone_number']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_configurations');
    }
};
