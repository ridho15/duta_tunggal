<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'do_number' => $this->faker->unique()->word(),
            'delivery_date' => $this->faker->date(),
            'driver_id' => 1, // Use static ID for testing
            'vehicle_id' => 1, // Use static ID for testing
            'notes' => $this->faker->sentence(),
            'warehouse_id' => \App\Models\Warehouse::factory(),
            'status' => 'draft',
        ];
    }
}
