<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('output_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_template_id')
                ->constrained('package_templates')
                ->cascadeOnDelete();

            // ── Output Identity ─────────────────────────────────────────
            // This is the key that dependency_rules reference
            // e.g. "civil_ifc_eqpad_approved", "transformer_delivered"
            $table->string('output_key')->unique();
            $table->string('output_name');                    // Human-readable name

            // ── Output Type ─────────────────────────────────────────────
            // approval_milestone | delivery_milestone | completion_milestone | boundary_milestone
            $table->string('output_type')->default('approval_milestone');

            // ── Which activity in the template produces this output ──────
            // References activity_templates.sequence
            // Typically the final milestone activity
            $table->unsignedSmallInteger('produced_by_sequence');

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('package_template_id');
            $table->index('output_key');
        });
    }

    public function down()
    {
        Schema::dropIfExists('output_definitions');
    }
};
