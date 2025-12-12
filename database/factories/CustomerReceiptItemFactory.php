<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerReceiptItemFactory extends Factory
{
    protected $model = CustomerReceiptItem::class;

    public function definition(): array
    {
        return [
            'customer_receipt_id' => CustomerReceipt::factory(),
            'invoice_id' => Invoice::factory(),
            'method' => $this->faker->randomElement(['cash', 'bank_transfer', 'cheque', 'deposit', 'credit_card']),
            'amount' => number_format($this->faker->randomFloat(2, 10000, 1000000), 2, '.', ''),
            'coa_id' => ChartOfAccount::factory(),
            'payment_date' => $this->faker->date(),
            'selected_invoices' => null,
        ];
    }
}