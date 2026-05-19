<?php

namespace Database\Factories;

use App\Models\Product;
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
            'inventory_coa_id' => Product::resolveDefaultProductCoaId('inventory_coa_id'),
            'sales_coa_id' => Product::resolveDefaultProductCoaId('sales_coa_id'),
            'sales_return_coa_id' => Product::resolveDefaultProductCoaId('sales_return_coa_id'),
            'sales_discount_coa_id' => Product::resolveDefaultProductCoaId('sales_discount_coa_id'),
            'goods_delivery_coa_id' => Product::resolveDefaultProductCoaId('goods_delivery_coa_id'),
            'cogs_coa_id' => Product::resolveDefaultProductCoaId('cogs_coa_id'),
            'purchase_return_coa_id' => Product::resolveDefaultProductCoaId('purchase_return_coa_id'),
            'unbilled_purchase_coa_id' => Product::resolveDefaultProductCoaId('unbilled_purchase_coa_id'),
            'temporary_procurement_coa_id' => Product::resolveDefaultProductCoaId('temporary_procurement_coa_id'),
            'manufacturing_labor_coa_id' => Product::resolveDefaultProductCoaId('manufacturing_labor_coa_id'),
            'manufacturing_overhead_coa_id' => Product::resolveDefaultProductCoaId('manufacturing_overhead_coa_id'),
        ];
    }

    public function forCabang(Cabang|int|null $cabang = null): static
    {
        return $this->state(function (array $attributes) use ($cabang) {
            $cabangId = match (true) {
                $cabang instanceof Cabang => $cabang->id,
                is_int($cabang) && $cabang > 0 => $cabang,
                default => Cabang::query()->inRandomOrder()->value('id')
                    ?? Cabang::factory()->create()->id,
            };

            return [
                'cabang_id' => $cabangId,
            ];
        });
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
