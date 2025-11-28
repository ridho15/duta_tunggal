<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
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