<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\ProductCategory;
use App\Models\Supplier;
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
        $category = ProductCategory::inRandomOrder()->first()
            ?? ProductCategory::factory()->create();

        return [
            'sku' => 'SKU-' . $this->faker->unique()->numerify('###'),
            'name' => 'Produk ' . $this->faker->word,
            'supplier_id' => Supplier::inRandomOrder()->first()->id ?? Supplier::factory()->create()->id,
            'product_category_id' => $category->id,
            'cabang_id' => Cabang::inRandomOrder()->first()->id ?? Cabang::factory()->create()->id,
            'uom_id' => optional(UnitOfMeasure::inRandomOrder()->first())->id ?? UnitOfMeasure::factory()->create()->id,
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
            'inventory_coa_id' => $this->resolveCoaId('1140.10'),
            'sales_coa_id' => $this->resolveCoaId('4100.10'),
            'sales_return_coa_id' => $this->resolveCoaId('4120.10'),
            'sales_discount_coa_id' => $this->resolveCoaId('4110.10'),
            'goods_delivery_coa_id' => $this->resolveCoaId('1140.20'),
            'cogs_coa_id' => $this->resolveCoaId('5100.10'),
            'purchase_return_coa_id' => $this->resolveCoaId('5120.10'),
            'unbilled_purchase_coa_id' => $this->resolveCoaId('2190.10'),
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

    private function resolveCoaId(string $code): ?int
    {
        static $cache = [];

        if (array_key_exists($code, $cache)) {
            if ($cache[$code] && ! ChartOfAccount::whereKey($cache[$code])->exists()) {
                unset($cache[$code]);
            } else {
                return $cache[$code];
            }
        }

        $cache[$code] = ChartOfAccount::where('code', $code)->value('id');

        return $cache[$code];
    }
}
