<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('validation_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // ── Rule Reference ──────────────────────────────────────────
            $table->string('rule_class');                    // e.g. "MissingOutputProviderRule"

            // ── Severity ────────────────────────────────────────────────
            // critical | warning | info
            $table->string('severity')->default('critical');

            // ── Error Details ───────────────────────────────────────────
            $table->text('message');
            $table->json('context')->nullable();             // Structured data for UI display
            // e.g. {"output_key": "...", "consumer": "..."}

            // ── Resolution ──────────────────────────────────────────────
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            // ── Validation Run ──────────────────────────────────────────
            $table->timestamp('validated_at')->nullable();    // When this error was detected

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('project_id');
            $table->index(['project_id', 'severity']);
            $table->index(['project_id', 'resolved']);
            $table->index('rule_class');
        });
    }

    public function down()
    {
        Schema::dropIfExists('validation_errors');
    }
};
