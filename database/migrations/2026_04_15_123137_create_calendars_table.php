<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. "5 Day Workweek", "6 Day Workweek"
            $table->unsignedTinyInteger('workdays_per_week')->default(5);
            $table->json('working_days')->nullable();        // e.g. [1,2,3,4,5] (Mon-Fri)
            $table->json('holidays')->nullable();            // array of date strings
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('calendars');
    }
};
