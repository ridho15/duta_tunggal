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
        return [
            'invoice_id' => 1, // will be overridden
            'product_id' => 1, // will be overridden
            'quantity' => $this->faker->numberBetween(1, 100),
            'price' => $this->faker->numberBetween(1000, 100000),
            'total' => $this->faker->numberBetween(1000, 10000000),
        ];
    }
}
