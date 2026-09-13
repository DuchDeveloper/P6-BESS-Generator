<?php

namespace Database\Factories;

use App\Models\DependencyRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class DependencyRuleFactory extends Factory
{
    protected $model = DependencyRule::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
