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
            $table->unsignedTinyInteger('zone_number');
            $table->string('zone_label')->nullable();

            // ── Zone Configuration ───────────────────────────────────────
            $table->unsignedTinyInteger('block_count');

            // ── Calculated totals ────────────────────────────────────────
            $table->unsignedSmallInteger('total_batteries')->default(0);
            $table->unsignedSmallInteger('total_groups')->default(0);

            $table->timestamps();

            $table->unique(['project_id', 'zone_number']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_configurations');
    }
};