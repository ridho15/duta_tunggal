<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rak;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    public function definition(): array
    {
        // Get random product and warehouse
        $product = \App\Models\Product::inRandomOrder()->first();
        $warehouse = \App\Models\Warehouse::has('rak')->inRandomOrder()->first();

        // If no warehouse with rak found, create one
        if (!$warehouse) {
            $warehouse = \App\Models\Warehouse::factory()->create();
            \App\Models\Rak::factory()->create(['warehouse_id' => $warehouse->id]);
        }

        // If no product found, create one
        if (!$product) {
            $product = \App\Models\Product::factory()->create();
        }

        // Find rak for this warehouse
        $rak = \App\Models\Rak::where('warehouse_id', $warehouse->id)
            ->inRandomOrder()
            ->first();

        return [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak?->id, // pakai safe navigation
            'qty_available' => $this->faker->numberBetween(10, 100),
            'qty_reserved' => $this->faker->numberBetween(10, 100),
            'qty_min' => $this->faker->numberBetween(10, 20),
        ];
    }
}
