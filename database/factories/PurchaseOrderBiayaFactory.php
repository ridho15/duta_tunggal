<?php

namespace Database\Factories;

use App\Models\PurchaseOrderBiaya;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderBiayaFactory extends Factory
{
    protected $model = PurchaseOrderBiaya::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => null, // Will be set when creating
            'currency_id' => null, // Will be set when creating
            'coa_id' => null, // Will be set when creating
            'nama_biaya' => $this->faker->word(),
            'total' => $this->faker->numberBetween(10000, 100000),
            'untuk_pembelian' => $this->faker->boolean(),
            'masuk_invoice' => $this->faker->boolean(),
        ];
    }
}