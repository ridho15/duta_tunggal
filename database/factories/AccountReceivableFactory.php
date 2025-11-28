<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountReceivable>
 */
class AccountReceivableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = $this->faker->numberBetween(100000, 5000000);
        $paid = $this->faker->numberBetween(0, $total);
        $remaining = $total - $paid;

        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $remaining > 0 ? 'Belum Lunas' : 'Lunas',
            'created_by' => 1,
        ];
    }
}