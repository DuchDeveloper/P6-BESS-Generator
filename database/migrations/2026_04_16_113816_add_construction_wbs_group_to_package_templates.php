<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_templates', function (Blueprint $table) {
            // Construction WBS sub-group: site_mobilisation | substation | bess
            $table->string('construction_wbs_group')->nullable()->after('wbs_category');
        });
    }

    public function down(): void
    {
        Schema::table('package_templates', function (Blueprint $table) {
            $table->dropColumn('construction_wbs_group');
        });
    }
};