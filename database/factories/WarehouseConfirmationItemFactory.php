<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarehouseConfirmationItem>
 */
class WarehouseConfirmationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_confirmation_id' => \App\Models\WarehouseConfirmation::factory(),
            'sale_order_item_id' => \App\Models\SaleOrderItem::factory(),
            'confirmed_qty' => $this->faker->randomFloat(2, 1, 100),
            'warehouse_id' => \App\Models\Warehouse::factory(),
            'rak_id' => \App\Models\Rak::factory(),
            'status' => 'confirmed',
        ];
    }
}
