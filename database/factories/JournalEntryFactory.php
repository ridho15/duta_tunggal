<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\CustomerReceiptItem;
use App\Models\VendorPaymentDetail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $debitOrCredit = $this->faker->boolean();
        $sourceType = $this->faker->randomElement([
            'App\\Models\\VendorPaymentDetail',
            'App\\Models\\CustomerReceiptItem',
        ]);

        if ($sourceType == 'App\\Models\\VendorPaymentDetail') {
            $source_id = VendorPaymentDetail::inRandomOrder()->first()->id;
        } elseif ($sourceType == 'App\\Models\\CustomerReceiptItem') {
            $source_id = CustomerReceiptItem::inRandomOrder()->first()->id;
        }
        return [
            'coa_id'        => ChartOfAccount::inRandomOrder()->first()->id, // Pastikan id COA ini ada di chart_of_accounts
            'date'          => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'reference'     => 'REF-' . strtoupper(Str::random(5)),
            'description'   => $this->faker->sentence(),
            'debit'         => $debitOrCredit ? $this->faker->randomFloat(2, 10000, 500000) : 0,
            'credit'        => !$debitOrCredit ? $this->faker->randomFloat(2, 10000, 500000) : 0,
            'journal_type'  => $this->faker->randomElement(['sales', 'purchase', 'manual']),
            'source_type'   => $sourceType,
            'source_id'     => $source_id, // Asumsikan ada 20 data dummy dari source tersebut
        ];
    }
}
