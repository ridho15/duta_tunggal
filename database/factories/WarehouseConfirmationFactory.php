<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarehouseConfirmation>
 */
class WarehouseConfirmationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_order_id' => \App\Models\SaleOrder::factory(),
            'manufacturing_order_id' => null,
            'status' => 'Confirmed',
            'note' => $this->faker->sentence(),
            'confirmed_by' => \App\Models\User::factory(),
            'confirmed_at' => now(),
        ];
    }
}
