<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cabang = Cabang::inRandomOrder()->first() ?? Cabang::factory()->create();
        $category = ProductCategory::where('cabang_id', $cabang->id)->inRandomOrder()->first()
            ?? ProductCategory::factory()->create(['cabang_id' => $cabang->id]);

        return [
            'sku' => 'SKU-' . $this->faker->unique()->numerify('###'),
            'name' => 'Produk ' . $this->faker->word,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'uom_id' => UnitOfMeasure::inRandomOrder()->first()->id,
            'cost_price' => $this->faker->randomFloat(2, 5000, 100000),
            'sell_price' => $this->faker->randomFloat(2, 10000, 200000),
            'biaya' => $this->faker->randomFloat(2, 1000, 5000),
            'harga_batas' => $this->faker->randomFloat(2, 0, 20),
            'item_value' => $this->faker->randomFloat(2, 5000, 50000),
            'tipe_pajak' => $this->faker->randomElement(['Non Pajak', 'Inklusif', 'Eksklusif']),
            'pajak' => $this->faker->randomFloat(2, 0, 10),
            'jumlah_kelipatan_gudang_besar' => $this->faker->numberBetween(1, 50),
            'jumlah_jual_kategori_banyak' => $this->faker->numberBetween(1, 100),
            'kode_merk' => 'MRK-' . $this->faker->unique()->numerify('###'),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Product $product) {
            // Tambahkan 2-3 konversi satuan dummy untuk setiap produk
            $product->unitConversions()->create([
                'uom_id' => UnitOfMeasure::inRandomOrder()->first()->id,
                'nilai_konversi' => $this->faker->randomFloat(2, 1, 20),
            ]);
        });
    }
}
