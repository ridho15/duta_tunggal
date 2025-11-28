<?php

namespace Database\Seeders;

use App\Models\InventoryStock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InventoryStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create inventory stocks with unique product-warehouse combinations
        $created = 0;
        $attempts = 0;
        $maxAttempts = 200; // Prevent infinite loops

        while ($created < 50 && $attempts < $maxAttempts) {
            $attempts++;

            // Get random product and warehouse
            $product = \App\Models\Product::inRandomOrder()->first();
            $warehouse = \App\Models\Warehouse::has('rak')->inRandomOrder()->first();

            // If no warehouse with rak found, create one
            if (!$warehouse) {
                $warehouse = \App\Models\Warehouse::factory()->create();
                \App\Models\Rak::factory()->create(['warehouse_id' => $warehouse->id]);
                $warehouse = \App\Models\Warehouse::find($warehouse->id); // Refresh
            }

            // If no product found, create one
            if (!$product) {
                $product = \App\Models\Product::factory()->create();
            }

            // Find rak for this warehouse
            $rak = \App\Models\Rak::where('warehouse_id', $warehouse->id)
                ->inRandomOrder()
                ->first();

            // Try to create or update inventory stock
            $inventoryStock = \App\Models\InventoryStock::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'rak_id' => $rak?->id,
                    'qty_available' => fake()->numberBetween(10, 100),
                    'qty_reserved' => fake()->numberBetween(0, 50),
                    'qty_min' => fake()->numberBetween(5, 20),
                ]
            );

            // If this is a new record (not updated), count it
            if ($inventoryStock->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info("Created/updated {$created} inventory stock records after {$attempts} attempts.");
    }
}
