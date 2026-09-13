<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('dependency_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_template_id')
                ->constrained('package_templates')
                ->cascadeOnDelete();

            // ── What This Package Requires ──────────────────────────────
            // References output_definitions.output_key
            // The DependencyResolverService uses this key to find the provider
            $table->string('required_output_key');

            // ── Entry Anchor ────────────────────────────────────────────
            // Which activity in the consumer package this output gates
            // References activity_templates.sequence (usually sequence 1)
            $table->unsignedSmallInteger('gates_activity_sequence')->default(1);

            // ── Mandatory Flag ──────────────────────────────────────────
            // If true: missing provider = critical validation error
            // If false: missing provider = warning only
            $table->boolean('is_mandatory')->default(true);

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('consumer_template_id');
            $table->index('required_output_key');
            $table->unique(['consumer_template_id', 'required_output_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('dependency_rules');
    }
};
