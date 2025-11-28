<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rak;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();
        $fromRak = Rak::factory()->create(['warehouse_id' => $fromWarehouse->id]);
        $toRak = Rak::factory()->create(['warehouse_id' => $toWarehouse->id]);

        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'from_warehouse_id' => $fromWarehouse->id,
            'from_rak_id' => $fromRak->id,
            'to_warehouse_id' => $toWarehouse->id,
            'to_rak_id' => $toRak->id,
        ];
    }
}