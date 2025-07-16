<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_number' => $this->faker->unique()->word(),
            'customer_id' => Customer::inRandomOrder()->first()->id,
            'date' => $this->faker->date(),
            'notes' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['draft', 'request_approve', 'approve', 'reject'])
        ];
    }
}
