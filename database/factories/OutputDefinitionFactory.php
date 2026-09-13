<?php

namespace Database\Factories;

use App\Models\OutputDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class OutputDefinitionFactory extends Factory
{
    protected $model = OutputDefinition::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
