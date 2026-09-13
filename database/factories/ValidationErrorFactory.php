<?php

namespace Database\Factories;

use App\Models\ValidationError;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ValidationErrorFactory extends Factory
{
    protected $model = ValidationError::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
