<?php

namespace Database\Factories;

use App\Models\ProviderResolution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProviderResolutionFactory extends Factory
{
    protected $model = ProviderResolution::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
