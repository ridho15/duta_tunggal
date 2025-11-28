<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerReceiptFactory extends Factory
{
    protected $model = CustomerReceipt::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'selected_invoices' => null,
            'invoice_receipts' => null,
            'payment_date' => $this->faker->date(),
            'ntpn' => $this->faker->unique()->numerify('RCP-##########'),
            'total_payment' => number_format($this->faker->randomFloat(2, 100000, 10000000), 2, '.', ''),
            'notes' => $this->faker->optional()->sentence(),
            'diskon' => number_format($this->faker->randomFloat(2, 0, 100000), 2, '.', ''),
            'payment_adjustment' => number_format($this->faker->randomFloat(2, 0, 50000), 2, '.', ''),
            'payment_method' => $this->faker->randomElement(['cash', 'bank_transfer', 'cheque', 'deposit', 'credit_card']),
            'coa_id' => ChartOfAccount::factory(),
            'status' => $this->faker->randomElement(['Draft', 'Partial', 'Paid']),
        ];
    }

    public function cash(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_method' => 'cash',
            ];
        });
    }

    public function bankTransfer(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_method' => 'bank_transfer',
            ];
        });
    }

    public function paid(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'Paid',
            ];
        });
    }
}