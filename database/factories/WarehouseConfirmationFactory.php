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
            'confirmable_type' => \App\Models\SaleOrder::class,
            'confirmable_id'   => \App\Models\SaleOrder::factory(),
            'confirmation_type' => 'sales_order',
            'status'           => 'confirmed',
            'note'             => $this->faker->sentence(),
            'confirmed_by'     => \App\Models\User::factory(),
            'confirmed_at'     => now(),
        ];
    }
}
