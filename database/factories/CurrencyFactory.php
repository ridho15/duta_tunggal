<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;
    public function definition(): array
    {
        $currencies = [
            ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1, 'code' => 'IDR'],
            ['name' => 'US Dollar', 'symbol' => '$', 'to_rupiah' => 15000, 'code' => 'USD'],
            ['name' => 'Euro', 'symbol' => '€', 'to_rupiah' => 16000, 'code' => 'EUR'],
            ['name' => 'Japanese Yen', 'symbol' => '¥', 'to_rupiah' => 100, 'code' => 'JPY'],
        ];

        $currency = $this->faker->randomElement($currencies);
        return [
            'name' => $currency['name'],
            'symbol' => $currency['symbol'],
            'to_rupiah' => $currency['to_rupiah'],
            'code' => $currency['code'],
        ];
    }
}
