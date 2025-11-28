<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\VendorPaymentDetail;
use App\Models\VendorPayment;
use App\Models\Invoice;
use App\Models\ChartOfAccount;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorPaymentDetail>
 */
class VendorPaymentDetailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VendorPaymentDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_payment_id' => VendorPayment::factory(),
            'invoice_id' => Invoice::factory(),
            'method' => $this->faker->randomElement(['Cash', 'Bank Transfer', 'Cheque', 'Credit', 'Deposit']),
            'amount' => number_format($this->faker->numberBetween(10000, 1000000), 2, '.', ''),
            'coa_id' => ChartOfAccount::factory(),
            'payment_date' => now(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}