<?php

namespace Database\Seeders;

use App\Models\StockMovement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create stock movements using existing products and warehouses
        $created = 0;
        $attempts = 0;
        $maxAttempts = 150; // Prevent infinite loops

        while ($created < 50 && $attempts < $maxAttempts) {
            $attempts++;

            try {
                // Get existing product and warehouse instead of creating new ones
                $product = \App\Models\Product::inRandomOrder()->first();
                $warehouse = \App\Models\Warehouse::inRandomOrder()->first();

                if (!$product || !$warehouse) {
                    continue; // Skip if no existing records
                }

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

                $value = fake()->randomFloat(2, 100, 1_000);

                \App\Models\StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => fake()->numberBetween(10, 100),
                    'value' => $value,
                    'type' => $type,
                    'reference_id' => fake()->word(),
                    'date' => fake()->dateTime(),
                    'meta' => ['faker' => true],
                ]);

                $created++;
            } catch (\Exception $e) {
                // Continue if there's any error
                continue;
            }
        }

        $this->command->info("Created {$created} stock movement records after {$attempts} attempts.");
    }
}
