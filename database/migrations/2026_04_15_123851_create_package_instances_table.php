<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('package_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignId('template_id')
                ->constrained('package_templates')
                ->restrictOnDelete();
            $table->foreignId('wbs_node_id')
                ->nullable()
                ->constrained('wbs_nodes')
                ->nullOnDelete();

            // ── Ownership State ─────────────────────────────────────────
            // internal | external | included_elsewhere | not_applicable
            $table->string('ownership_mode')->default('internal');

            // If included_elsewhere: which other package_instance contains this scope
            $table->foreignId('included_in_package_id')
                ->nullable()
                ->constrained('package_instances')
                ->nullOnDelete();

            // ── Selection State ─────────────────────────────────────────
            $table->boolean('selected')->default(true);
            $table->boolean('applicable')->default(true);
            $table->text('exclusion_reason')->nullable();    // Why not_applicable or excluded

            // ── Topology Context ────────────────────────────────────────
            // Populated for topology-driven packages (Battery/PCS/SUT groups)
            $table->unsignedTinyInteger('zone_number')->nullable();
            $table->unsignedTinyInteger('block_number')->nullable();
            $table->unsignedTinyInteger('group_number')->nullable();
            $table->string('topology_label')->nullable();    // e.g. "Zone 1 Block 2 Battery Group 3"

            // ── Override Fields ─────────────────────────────────────────
            // User can override the template name per instance
            $table->string('name_override')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('project_id');
            $table->index('template_id');
            $table->index(['project_id', 'ownership_mode']);
            $table->index(['project_id', 'selected', 'applicable']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('package_instances');
    }
};
