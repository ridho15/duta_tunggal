<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashBankTransfer>
 */
class CashBankTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TRF-' . now()->format('Ymd') . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'date' => now(),
            'from_coa_id' => \App\Models\ChartOfAccount::factory(),
            'to_coa_id' => \App\Models\ChartOfAccount::factory(),
            'amount' => $this->faker->numberBetween(1000, 100000),
            'other_costs' => 0,
            'other_costs_coa_id' => null,
            'description' => $this->faker->sentence,
            'status' => 'draft',
        ];
    }

    /**
     * Indicate that the transfer has admin fees
     */
    public function withAdminFee(): static
    {
        return $this->state(fn (array $attributes) => [
            'other_costs' => $this->faker->numberBetween(5000, 50000),
            'other_costs_coa_id' => \App\Models\ChartOfAccount::firstOrCreate(
                ['code' => '6999'],
                [
                    'name' => 'Biaya Admin Bank',
                    'type' => 'Expense',
                    'is_active' => true,
                ]
            )->id,
        ]);
    }
}
