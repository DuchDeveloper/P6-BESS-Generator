<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('package_templates', function (Blueprint $table) {
            $table->id();

            // ── Identity ────────────────────────────────────────────────
            $table->string('code')->unique();                // e.g. "civil_30_eqpad"
            $table->string('name');                          // e.g. "Civil 30% – Equipment Pad Design"

            // ── Classification ──────────────────────────────────────────
            // design | procurement | construction | commissioning | interface | management | milestone
            $table->string('type');

            // civil | electrical_primary | electrical_secondary | scada |
            // hvac | fire | commissioning_docs | design_management | none
            $table->string('discipline');

            // milestones | management | design | procurement | construction | commissioning
            $table->string('wbs_category');

            // Maturity level for design packages
            // basis | 30 | 60 | 90 | ifc | ifc_afc | freeze | null (non-design)
            $table->string('maturity_level')->nullable();

            // ── Ownership ───────────────────────────────────────────────
            // internal | external | included_elsewhere | not_applicable
            $table->string('ownership_mode_default')->default('internal');

            // ── Conditionality ──────────────────────────────────────────
            $table->boolean('is_optional')->default(true);
            $table->boolean('is_conditional')->default(false);   // Driven by a project flag
            $table->string('condition_flag')->nullable();         // e.g. "control_room_exists"
            $table->boolean('is_topology_driven')->default(false); // Generated per zone/block/group

            // ── Design Management Special Behaviour ─────────────────────
            $table->boolean('has_dynamic_predecessors')->default(false); // True for MDR packages
            $table->string('dynamic_predecessor_maturity')->nullable();  // "30" | "60" | "90" | "ifc"

            // ── Sorting ─────────────────────────────────────────────────
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('type');
            $table->index('discipline');
            $table->index('wbs_category');
            $table->index('maturity_level');
        });
    }

    public function down()
    {
        Schema::dropIfExists('package_templates');
    }
};
