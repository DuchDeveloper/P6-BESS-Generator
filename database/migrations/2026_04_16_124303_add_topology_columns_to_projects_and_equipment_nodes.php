<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_battery_count')->nullable()->after('suts_per_block');
        });

        Schema::table('equipment_nodes', function (Blueprint $table) {
            $table->boolean('is_partial')->default(false)->after('ready_output_key');
            $table->unsignedSmallInteger('actual_unit_count')->nullable()->after('is_partial');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('total_battery_count');
        });

        Schema::table('equipment_nodes', function (Blueprint $table) {
            $table->dropColumn(['is_partial', 'actual_unit_count']);
        });
    }
};