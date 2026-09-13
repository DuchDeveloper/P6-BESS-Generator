<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('energisation_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // ── Milestone Definition ────────────────────────────────────
            $table->string('name');                          // e.g. "Switchroom Energised"
            $table->string('output_key');                    // e.g. "switchroom_energised"

            // ── Sequence ────────────────────────────────────────────────
            // Defines the energisation sequence order
            $table->unsignedTinyInteger('sequence');

            // ── Conditions ──────────────────────────────────────────────
            $table->boolean('selected')->default(true);
            $table->boolean('applicable')->default(true);

            // ── Linked Activity Instance ────────────────────────────────
            $table->foreignId('activity_instance_id')
                ->nullable()
                ->constrained('activity_instances')
                ->nullOnDelete();

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('project_id');
            $table->unique(['project_id', 'output_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('energisation_milestones');
    }
};
