<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleOrder>
 */
class SaleOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer_id = Customer::inRandomOrder()->first()->id;
        $so_number = $this->faker->unique()->word();
        return [
            'customer_id' => $customer_id,
            'so_number' => $so_number,
            'order_date' => $this->faker->date(),
            'total_amount' => 0,
            'shipped_to' => $this->faker->address()
        ];
    }
}
