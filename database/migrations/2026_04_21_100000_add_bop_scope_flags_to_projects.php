<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Buildings and facilities (rare — default false)
            $table->boolean('oam_building_exists')->default(false);
            $table->boolean('guardhouse_exists')->default(false);
            $table->boolean('site_office_exists')->default(false);

            // Site-wide civil / infrastructure (common — default true, except potable water)
            $table->boolean('perimeter_fencing_exists')->default(true);
            $table->boolean('access_roads_exists')->default(true);
            $table->boolean('site_drainage_exists')->default(true);
            $table->boolean('potable_water_exists')->default(false);

            // Site-wide E&I (mixed)
            $table->boolean('external_lighting_exists')->default(true);
            $table->boolean('cctv_security_exists')->default(false);
            $table->boolean('access_control_exists')->default(false);
            $table->boolean('public_address_exists')->default(false);
            $table->boolean('site_comms_backbone_exists')->default(false);
            $table->boolean('site_ups_exists')->default(true);

            // Safety and fire (BOP-specific)
            $table->boolean('building_fire_system_exists')->default(true);
            $table->boolean('lightning_protection_site_exists')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'oam_building_exists',
                'guardhouse_exists',
                'site_office_exists',
                'perimeter_fencing_exists',
                'access_roads_exists',
                'site_drainage_exists',
                'potable_water_exists',
                'external_lighting_exists',
                'cctv_security_exists',
                'access_control_exists',
                'public_address_exists',
                'site_comms_backbone_exists',
                'site_ups_exists',
                'building_fire_system_exists',
                'lightning_protection_site_exists',
            ]);
        });
    }
};