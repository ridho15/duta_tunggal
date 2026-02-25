<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseReceiptItem>
 */
class PurchaseReceiptItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_receipt_id' => 1, // will be overridden
            'purchase_order_item_id' => 1, // will be overridden
            'product_id' => 1, // will be overridden
            'qty_received' => $this->faker->numberBetween(1, 100),
            'qty_accepted' => $this->faker->numberBetween(1, 100),
            'qty_rejected' => $this->faker->numberBetween(0, 10),
            'reason_rejected' => $this->faker->optional()->sentence(),
            'warehouse_id' => 1, // will be overridden
            'status' => 'pending',
            'rak_id' => $this->faker->optional()->numberBetween(1, 10),
        ];
    }
}
