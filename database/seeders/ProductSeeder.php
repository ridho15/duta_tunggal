<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Cabang;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultAccountIds = Product::resolveDefaultProductCoaMap();

        // Create 50 products using updateOrCreate to handle duplicates
        for ($i = 1; $i <= 50; $i++) {
            $this->createOrUpdateProduct($i, $defaultAccountIds);
        }

        $this->command->info('50 products created/updated successfully!');
    }

    private function createOrUpdateProduct($index, array $defaultAccountIds)
    {
        $cabang = Cabang::inRandomOrder()->first() ?? Cabang::factory()->create();
        $category = ProductCategory::inRandomOrder()->first()
            ?? ProductCategory::factory()->create();

        $sku = 'SKU-' . str_pad($index, 3, '0', STR_PAD_LEFT);
        $supplier = Supplier::inRandomOrder()->first() ?? Supplier::factory()->create();
        $uom = UnitOfMeasure::inRandomOrder()->first() ?? UnitOfMeasure::factory()->create();
        

        Product::updateOrCreate(
            ['sku' => $sku], // Find by SKU
            [
                'name' => 'Produk ' . fake()->word . ' ' . $index,
                'supplier_id' => $supplier->id,
                'product_category_id' => $category->id,
                'cabang_id' => $cabang->id,
                'uom_id' => $uom->id,
                'cost_price' => fake()->numberBetween(5000, 100000),
                'sell_price' => fake()->numberBetween(10000, 200000),
                'biaya' => fake()->numberBetween(1000, 5000),
                'harga_batas' => fake()->randomFloat(2, 0, 20),
                'item_value' => fake()->numberBetween(5000, 50000),
                'tipe_pajak' => fake()->randomElement(['Non Pajak', 'Inklusif', 'Eksklusif']),
                'pajak' => fake()->randomFloat(2, 0, 10),
                'jumlah_kelipatan_gudang_besar' => fake()->numberBetween(1, 50),
                'jumlah_jual_kategori_banyak' => fake()->numberBetween(1, 100),
                'kode_merk' => 'MRK-' . str_pad($index, 3, '0', STR_PAD_LEFT),
                'inventory_coa_id' => $defaultAccountIds['inventory_coa_id'] ?? null,
                'sales_coa_id' => $defaultAccountIds['sales_coa_id'] ?? null,
                'sales_return_coa_id' => $defaultAccountIds['sales_return_coa_id'] ?? null,
                'sales_discount_coa_id' => $defaultAccountIds['sales_discount_coa_id'] ?? null,
                'goods_delivery_coa_id' => $defaultAccountIds['goods_delivery_coa_id'] ?? null,
                'cogs_coa_id' => $defaultAccountIds['cogs_coa_id'] ?? null,
                'purchase_return_coa_id' => $defaultAccountIds['purchase_return_coa_id'] ?? null,
                'unbilled_purchase_coa_id' => $defaultAccountIds['unbilled_purchase_coa_id'] ?? null,
                'temporary_procurement_coa_id' => $defaultAccountIds['temporary_procurement_coa_id'] ?? null,
                'manufacturing_labor_coa_id' => $defaultAccountIds['manufacturing_labor_coa_id'] ?? null,
                'manufacturing_overhead_coa_id' => $defaultAccountIds['manufacturing_overhead_coa_id'] ?? null,
            ]
        );
    }
}
