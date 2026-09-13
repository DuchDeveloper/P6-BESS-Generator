<?php

namespace Database\Factories;

use App\Models\EnergisationMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EnergisationMilestoneFactory extends Factory
{
    protected $model = EnergisationMilestone::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
