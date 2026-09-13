<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('provider_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // ── What Output Key Is Being Resolved ───────────────────────
            $table->string('output_key');                    // e.g. "civil_ifc_eqpad_approved"

            // ── Who Provides It ─────────────────────────────────────────
            $table->foreignId('provider_package_instance_id')
                ->nullable()
                ->constrained('package_instances')
                ->nullOnDelete();

            // The specific activity instance that is the output milestone
            $table->foreignId('provider_activity_instance_id')
                ->nullable()
                ->constrained('activity_instances')
                ->nullOnDelete();

            // ── Resolution Type ─────────────────────────────────────────
            // internal   = provided by a selected internal package
            // external   = provided by a boundary receipt milestone
            // unresolved = no provider found (validation error state)
            $table->string('resolution_type')->default('unresolved');

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('project_id');
            $table->index('output_key');
            $table->unique(['project_id', 'output_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('provider_resolutions');
    }
};
