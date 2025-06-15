<?php

namespace Database\Factories;

use App\Models\DeliveryOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReturnProduct>
 */
class ReturnProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_number' => $this->faker->unique()->word(),
            'from_model_id' => DeliveryOrder::inRandomOrder()->first()->id,
            'from_model_type' => 'App\Models\DeliveryOrder',
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id,
            'reason' => $this->faker->sentence()
        ];
    }
}
