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
        $type = collect([
            'purchase_in',
            'sales',
            'transfer_in',
            'transfer_out',
            'manufacture_in',
            'manufacture_out',
            'adjustment_in',
            'adjustment_out',
        ])->random();

        $value = $this->faker->randomFloat(2, 100, 1_000);

        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => $this->faker->numberBetween(10, 100),
            'value' => $value,
            'type' => $type,
            'reference_id' => $this->faker->word(),
            'date' => $this->faker->dateTime(),
            'meta' => ['faker' => true],
        ];
    }
}
