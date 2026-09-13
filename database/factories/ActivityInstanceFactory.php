<?php

namespace Database\Factories;

use App\Models\ActivityInstance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ActivityInstanceFactory extends Factory
{
    protected $model = ActivityInstance::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
