<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('site_mobilisation_date')->nullable()->after('start_date');
            $table->date('mechanical_completion_date')->nullable()->after('site_mobilisation_date');
            $table->date('energisation_date')->nullable()->after('mechanical_completion_date');
            $table->date('commissioning_finish_date')->nullable()->after('energisation_date');
            $table->date('practical_completion_date')->nullable()->after('commissioning_finish_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'site_mobilisation_date',
                'mechanical_completion_date',
                'energisation_date',
                'commissioning_finish_date',
                'practical_completion_date',
            ]);
        });
    }
};