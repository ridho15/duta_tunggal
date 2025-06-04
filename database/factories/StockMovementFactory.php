<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        $type = ['purchase' => 'purchase', 'sales' => 'sales', 'transfer_in' => 'transfer_in', 'transfer_out' => 'transfer_out', 'manufacture_in' => 'manufacture_in', 'manufacture_out' => 'manufacture_out', 'adjustment' => 'adjustment'];
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => $this->faker->numberBetween(10, 100),
            'type' => array_rand($type),
            'reference_id' => $this->faker->word(),
            'date' => $this->faker->dateTime()
        ];
    }
}
