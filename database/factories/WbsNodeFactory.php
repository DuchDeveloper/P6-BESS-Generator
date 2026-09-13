<?php

namespace Database\Factories;

use App\Models\WbsNode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class WbsNodeFactory extends Factory
{
    protected $model = WbsNode::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
