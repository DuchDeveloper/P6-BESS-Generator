<?php

namespace Database\Factories;

use App\Models\InterfaceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class InterfaceRecordFactory extends Factory
{
    protected $model = InterfaceRecord::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
