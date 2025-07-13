<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cabang>
 */
class CabangFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => 'CBG-' . $this->faker->unique()->numerify('###'),
            'nama' => 'Cabang ' . $this->faker->city,
            'alamat' => $this->faker->address,
            'telepon' => $this->faker->phoneNumber,
            'kenaikan_harga' => $this->faker->randomFloat(2, 0, 20), // antara 0% - 20%
            'status' => $this->faker->boolean(50),
            'warna_background' => $this->faker->safeHexColor,
            'tipe_penjualan' => $this->faker->randomElement(['Semua', 'Pajak', 'Non Pajak']),
            'kode_invoice_pajak' => 'INV-PJK-' . $this->faker->unique()->numerify('###'),
            'kode_invoice_non_pajak' => 'INV-NPJK-' . $this->faker->unique()->numerify('###'),
            'kode_invoice_pajak_walkin' => 'INV-WPJK-' . $this->faker->unique()->numerify('###'),
            'nama_kwitansi' => 'Kwitansi ' . $this->faker->company,
            'label_invoice_pajak' => 'Pajak ' . $this->faker->word,
            'label_invoice_non_pajak' => 'Non Pajak ' . $this->faker->word,
            'logo_invoice_non_pajak' => null, // kosong dulu
            'lihat_stok_cabang_lain' => $this->faker->boolean,
        ];
    }
}
