<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 100);
        $price    = $this->faker->numberBetween(1000, 100000);
        $subtotal = $quantity * $price;

        return [
            'invoice_id'  => 1, // will be overridden
            'product_id'  => 1, // will be overridden
            'quantity'    => $quantity,
            'price'       => $price,
            'discount'    => 0,
            'tax_rate'    => 0,
            'tax_amount'  => 0,
            'subtotal'    => $subtotal,
            'total'       => $subtotal,
        ];
    }
}
