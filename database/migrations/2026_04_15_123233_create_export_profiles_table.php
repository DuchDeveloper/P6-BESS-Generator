<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('export_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. "Primavera P6 XER", "Excel"
            $table->string('format');                        // xer | excel | json
            $table->string('p6_version')->nullable();        // e.g. "22.12" for XER compatibility
            $table->json('settings')->nullable();            // format-specific settings
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('export_profiles');
    }
};
