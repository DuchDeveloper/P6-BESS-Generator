<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // ── Project Identity ────────────────────────────────────────
            $table->string('name');
            $table->string('code')->unique();                // Short project code e.g. "BESS-001"
            $table->string('client')->nullable();
            $table->text('description')->nullable();

            // ── Schedule Settings ───────────────────────────────────────
            $table->foreignId('calendar_id')
                ->constrained('calendars')
                ->restrictOnDelete();
            $table->foreignId('export_profile_id')
                ->nullable()
                ->constrained('export_profiles')
                ->nullOnDelete();
            $table->date('start_date');

            // ── Delivery Model ──────────────────────────────────────────
            // epc | bop_free_issued | owner_provided_design | construct_only | split_contract
            $table->string('delivery_model')->default('epc');

            // ── Scope Flags ─────────────────────────────────────────────
            $table->boolean('bess_free_issued')->default(false);
            $table->boolean('switchroom_exists')->default(true);
            $table->boolean('control_room_exists')->default(false);
            $table->boolean('transformer_exists')->default(true);
            $table->boolean('substation_exists')->default(true);
            $table->boolean('scada_included')->default(true);
            $table->boolean('hvac_in_vendor_package')->default(false);  // HVAC included in switchroom vendor scope
            $table->boolean('fire_in_vendor_package')->default(false);  // Fire included in switchroom vendor scope

            // ── Topology Configuration ──────────────────────────────────
            $table->unsignedTinyInteger('zone_count')->default(1);
            $table->unsignedTinyInteger('blocks_per_zone')->default(1);
            $table->unsignedTinyInteger('batteries_per_group')->default(4);
            $table->unsignedTinyInteger('battery_groups_per_block')->default(5);
            $table->unsignedTinyInteger('pcs_per_group')->default(2);
            $table->unsignedTinyInteger('pcs_groups_per_block')->default(5);
            $table->unsignedTinyInteger('suts_per_block')->default(2);

            // ── WBS Profile ─────────────────────────────────────────────
            // default | custom
            $table->string('wbs_profile')->default('default');

            // ── Workflow State ──────────────────────────────────────────
            // draft | configured | compiled | validated | exported
            $table->string('status')->default('draft');
            $table->timestamp('compiled_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('exported_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ─────────────────────────────────────────────────
            $table->index('delivery_model');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
};
