<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SaleOrder;
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
        $saleOrderId = SaleOrder::inRandomOrder()->first()->id;
        $product = Product::inRandomOrder()->first();
        $quantity = random_int(1, 20);
        $unit_price = $product->sell_price;
        $discount = random_int(1000, 100000);
        $tax = random_int(1000, 100000);
        return [
            'sale_order_id' => $saleOrderId,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'discount' => $discount,
            'tax' => $tax
        ];
    }
}
