<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-' . strtoupper($this->faker->unique()->randomLetter() . $this->faker->unique()->randomLetter() . $this->faker->numberBetween(1000, 9999)),
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 1, // will be overridden
            'invoice_date' => now(),
            'subtotal' => number_format($this->faker->numberBetween(100000, 10000000), 2, '.', ''),
            'tax' => number_format($this->faker->numberBetween(0, 100000), 2, '.', ''),
            'other_fee' => [],
            'total' => number_format($this->faker->numberBetween(100000, 10000000), 2, '.', ''),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'ppn_rate' => 11,
            'dpp' => number_format($this->faker->numberBetween(100000, 10000000), 2, '.', ''),
            'customer_name' => $this->faker->optional()->company(),
            'customer_phone' => $this->faker->optional()->phoneNumber(),
            'supplier_name' => $this->faker->optional()->company(),
            'supplier_phone' => $this->faker->optional()->phoneNumber(),
            'delivery_orders' => [],
            'purchase_receipts' => [],
        ];
    }
}
