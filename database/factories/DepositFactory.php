<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Deposit;
use App\Models\ChartOfAccount;
use App\Models\Supplier;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deposit>
 */
class DepositFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Deposit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
    $amount = $this->faker->numberBetween(100000, 10000000);
    $usedAmount = $this->faker->numberBetween(0, $amount);
    $remainingAmount = $amount - $usedAmount;

        return [
            'from_model_type' => Supplier::class,
            'from_model_id' => Supplier::factory(),
            'amount' => number_format($amount, 2, '.', ''),
            'used_amount' => number_format($usedAmount, 2, '.', ''),
            'remaining_amount' => number_format($remainingAmount, 2, '.', ''),
            'coa_id' => ChartOfAccount::factory(),
            'note' => $this->faker->optional()->sentence(),
            'status' => $remainingAmount > 0 ? 'active' : 'closed',
            'created_by' => 1, // Default admin user
        ];
    }
}