<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quotation = Quotation::inRandomOrder()->first()->id;
        $product = Product::inRandomOrder()->first();
        $unit_price = $product->sell_price;
        $discount = random_int(0, 20000);
        $quantity = random_int(1, 20);
        $tax = random_int(0, 10000);
        $total_price = ($unit_price * $quantity) - $discount + $tax;
        return [
            'quotation_id' => $quotation,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'discount' => $discount,
            'tax' => $tax,
            'notes' => $this->faker->sentence(),
            'total_price' => $total_price,
        ];
    }
}
