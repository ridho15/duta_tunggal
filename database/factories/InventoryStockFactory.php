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
    protected static $uniqueCombinations = [];
    public function definition(): array
    {
        // 1️⃣ Pertama kali, ambil semua kombinasi dan kocok
        if (empty(self::$uniqueCombinations)) {
            $productIds = Product::pluck('id')->toArray();
            $warehouseIds = Warehouse::has('rak')->pluck('id')->toArray();

            $combinations = [];

            foreach ($productIds as $productId) {
                foreach ($warehouseIds as $warehouseId) {
                    $combinations[] = [
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                    ];
                }
            }

            shuffle($combinations);

            self::$uniqueCombinations = $combinations;
        }

        // 2️⃣ Ambil satu kombinasi, buang dari list supaya unik
        if (empty(self::$uniqueCombinations)) {
            throw new \Exception('No more unique product_id + warehouse_id combinations available.');
        }

        $combination = array_pop(self::$uniqueCombinations);

        // 3️⃣ Cari rak yang cocok
        $rak = Rak::where('warehouse_id', $combination['warehouse_id'])
            ->inRandomOrder()
            ->first();

        return [
            'product_id' => $combination['product_id'],
            'warehouse_id' => $combination['warehouse_id'],
            'rak_id' => $rak?->id, // pakai safe navigation
            'qty_available' => $this->faker->numberBetween(10, 100),
            'qty_reserved' => $this->faker->numberBetween(10, 100),
            'qty_min' => $this->faker->numberBetween(10, 20),
        ];
    }
}
