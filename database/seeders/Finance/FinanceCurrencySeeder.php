<?php

namespace Database\Seeders\Finance;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class FinanceCurrencySeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): void
    {
        $currencies = [
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'to_rupiah' => 15000],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'to_rupiah' => 16250],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'to_rupiah' => 100],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
