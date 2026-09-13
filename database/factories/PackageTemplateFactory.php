<?php

namespace Database\Factories;

use App\Models\PackageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PackageTemplateFactory extends Factory
{
    protected $model = PackageTemplate::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
