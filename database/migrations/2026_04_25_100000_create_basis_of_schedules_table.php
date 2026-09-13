<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('basis_of_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // Version is per-project; incremented on each regeneration.
            $table->unsignedInteger('version')->default(1);

            // status: draft | final | superseded
            $table->string('status')->default('draft');

            // Deterministic + LLM sections stored as structured JSON:
            //   header, calendar, topology, milestones, wbs,
            //   scope_narrative, delivery_narrative, sequencing_rationale,
            //   assumptions, risks, exclusions
            $table->json('sections');

            // Metadata about the generation run
            $table->string('llm_model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();

            // Hash of the project state at generation time — used to flag staleness.
            $table->string('project_fingerprint', 64)->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index(['project_id', 'version']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basis_of_schedules');
    }
};