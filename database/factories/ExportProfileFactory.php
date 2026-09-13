<?php

namespace Database\Factories;

use App\Models\ExportProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ExportProfileFactory extends Factory
{
    protected $model = ExportProfile::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
