<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleOrderItem>
 */
class SaleOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $warehouse = Warehouse::has('rak')->inRandomOrder()->first()->id;
        $rak = Rak::where('warehouse_id', $warehouse)->inRandomOrder()->first()->id;
        return [
            'sale_order_id' => SaleOrder::inRandomOrder()->first()->id, // akan di-set saat seeding
            'product_id'    => Product::inRandomOrder()->first()->id, // pastikan data produk tersedia
            'quantity'      => rand(1, 20),
            'unit_price'    => $this->faker->numberBetween(10000, 500000),
            'discount'      => $this->faker->numberBetween(0, 20),
            'tax'           => $this->faker->numberBetween(0, 11),
            'warehouse_id'  => $warehouse,
            'rak_id'        => $rak,
        ];
    }
}
