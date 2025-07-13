<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;
    public function definition(): array
    {
        return [
            'purchase_order_id' => 1, // akan di-set saat seeding
            'product_id' => rand(1, 10), // ganti sesuai jumlah produk
            'quantity' => rand(1, 20),
            'unit_price' => $this->faker->numberBetween(10000, 100000),
            'discount' => $this->faker->numberBetween(0, 20),
            'tax' => $this->faker->numberBetween(0, 11),
            'tipe_pajak' => $this->faker->randomElement(['Non Pajak', 'Inklusif', 'Eklusif']),
            'refer_item_model_id' => null,
            'refer_item_model_type' => null,
            'currency_id' => 1, // ID mata uang
        ];
    }
}
