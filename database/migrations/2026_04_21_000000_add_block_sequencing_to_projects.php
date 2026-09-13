<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('block_sequencing_mode', 16)
                ->default('FS')
                ->after('suts_per_block');
            $table->smallInteger('block_sequencing_lag_days')
                ->default(0)
                ->after('block_sequencing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['block_sequencing_mode', 'block_sequencing_lag_days']);
        });
    }
};