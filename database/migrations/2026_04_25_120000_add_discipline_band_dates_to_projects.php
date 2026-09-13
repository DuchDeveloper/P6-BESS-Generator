<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('design_freeze_date')->nullable()->after('start_date');
            $table->date('civil_completion_date')->nullable()->after('site_mobilisation_date');
            $table->date('mechanical_install_start_date')->nullable()->after('civil_completion_date');
            $table->date('mechanical_install_finish_date')->nullable()->after('mechanical_install_start_date');
            $table->date('electrical_install_start_date')->nullable()->after('mechanical_install_finish_date');
            $table->date('electrical_install_finish_date')->nullable()->after('electrical_install_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'design_freeze_date',
                'civil_completion_date',
                'mechanical_install_start_date',
                'mechanical_install_finish_date',
                'electrical_install_start_date',
                'electrical_install_finish_date',
            ]);
        });
    }
};