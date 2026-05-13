<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Warehouse;
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
        $warehouse = Warehouse::factory()->create();

        return [
            'do_number' => $this->faker->unique()->word(),
            'delivery_date' => $this->faker->date(),
            'driver_id' => 1, // Use static ID for testing
            'vehicle_id' => 1, // Use static ID for testing
            'notes' => $this->faker->sentence(),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'cabang_id' => $warehouse->cabang_id,
        ];
    }
}
