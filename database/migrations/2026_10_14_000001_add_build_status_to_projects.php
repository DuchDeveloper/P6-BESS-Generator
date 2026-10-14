<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track schedule builds that run in the background: where the latest build
 * is up to, when it ran and what it produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('build_status')->nullable()->after('status');   // queued | running | succeeded | failed
            $table->string('build_stage')->nullable()->after('build_status');
            $table->text('build_message')->nullable()->after('build_stage');
            $table->json('build_summary')->nullable()->after('build_message');
            $table->timestamp('build_queued_at')->nullable()->after('build_summary');
            $table->timestamp('build_started_at')->nullable()->after('build_queued_at');
            $table->timestamp('build_finished_at')->nullable()->after('build_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'build_status', 'build_stage', 'build_message', 'build_summary',
                'build_queued_at', 'build_started_at', 'build_finished_at',
            ]);
        });
    }
};
