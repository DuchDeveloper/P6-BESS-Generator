<?php

namespace Database\Factories;

use App\Models\PackageInstance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PackageInstanceFactory extends Factory
{
    protected $model = PackageInstance::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
