<?php

namespace Database\Factories;

use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountPayable>
 */
class AccountPayableFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AccountPayable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = $this->faker->numberBetween(100000, 1000000);
        $paid = $this->faker->numberBetween(0, $total);
        $remaining = $total - $paid;

        return [
            'invoice_id' => Invoice::factory(),
            'supplier_id' => Supplier::factory(),
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'currency_id' => null,
            'exchange_rate' => 1,
            'total_original' => $total,
            'paid_original' => $paid,
            'remaining_original' => $remaining,
            'status' => $remaining > 0 ? 'Belum Lunas' : 'Lunas',
            'created_by' => User::factory(),
        ];
    }
}
