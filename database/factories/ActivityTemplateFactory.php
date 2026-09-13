<?php

namespace Database\Factories;

use App\Models\ActivityTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ActivityTemplateFactory extends Factory
{
    protected $model = ActivityTemplate::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
