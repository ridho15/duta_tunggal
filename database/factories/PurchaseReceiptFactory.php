<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseReceipt>
 */
class PurchaseReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'RN-' . strtoupper(Str::random(6)),
            'purchase_order_id' => 1, // will be overridden
            'receipt_date' => now(),
            'received_by' => 1,
            'notes' => $this->faker->optional()->sentence(),
            'currency_id' => 1,
            'other_cost' => $this->faker->numberBetween(0, 10000),
            'status' => 'completed',
        ];
    }
}
