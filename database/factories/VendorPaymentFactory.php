<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorPayment>
 */
class VendorPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => \App\Models\Invoice::factory(), // will be overridden when needed
            'supplier_id' => \App\Models\Supplier::factory(), // will be overridden when needed
            'selected_invoices' => [],
            'invoice_receipts' => [],
            'payment_date' => now(),
            'ntpn' => $this->faker->optional()->randomNumber(9),
            'total_payment' => number_format($this->faker->numberBetween(100000, 10000000), 2, '.', ''),
            'coa_id' => null,
            'payment_method' => $this->faker->randomElement(['transfer', 'cash', 'check']),
            'notes' => $this->faker->optional()->sentence(),
            'diskon' => number_format($this->faker->numberBetween(0, 100000), 2, '.', ''),
            'payment_adjustment' => number_format($this->faker->numberBetween(-50000, 50000), 2, '.', ''),
            'status' => 'Draft',
            'is_import_payment' => false,
            'ppn_import_amount' => 0,
            'pph22_amount' => 0,
            'bea_masuk_amount' => 0,
        ];
    }
}
