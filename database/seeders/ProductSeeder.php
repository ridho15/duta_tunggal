<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
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
     * Default akun COA per produk.
     * @var array<string, int|null>
     */
    protected array $defaultAccountIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->defaultAccountIds = $this->resolveDefaultAccountIds();

        // Create 50 products using updateOrCreate to handle duplicates
        for ($i = 1; $i <= 50; $i++) {
            $this->createOrUpdateProduct($i);
        }

        $this->command->info('50 products created/updated successfully!');
    }

    private function createOrUpdateProduct($index)
    {
        $cabang = Cabang::inRandomOrder()->first() ?? Cabang::factory()->create();
        $category = ProductCategory::where('cabang_id', $cabang->id)->inRandomOrder()->first()
            ?? ProductCategory::factory()->create(['cabang_id' => $cabang->id]);

        $sku = 'SKU-' . str_pad($index, 3, '0', STR_PAD_LEFT);

        Product::updateOrCreate(
            ['sku' => $sku], // Find by SKU
            [
                'name' => 'Produk ' . fake()->word . ' ' . $index,
                'cabang_id' => $cabang->id,
                'supplier_id' => Supplier::inRandomOrder()->first()->id ?? Supplier::factory()->create()->id,
                'product_category_id' => $category->id,
                'uom_id' => UnitOfMeasure::inRandomOrder()->first()->id ?? UnitOfMeasure::factory()->create()->id,
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
                'inventory_coa_id' => $this->defaultAccountIds['inventory_coa_id'] ?? null,
                'sales_coa_id' => $this->defaultAccountIds['sales_coa_id'] ?? null,
                'sales_return_coa_id' => $this->defaultAccountIds['sales_return_coa_id'] ?? null,
                'sales_discount_coa_id' => $this->defaultAccountIds['sales_discount_coa_id'] ?? null,
                'goods_delivery_coa_id' => $this->defaultAccountIds['goods_delivery_coa_id'] ?? null,
                'cogs_coa_id' => $this->defaultAccountIds['cogs_coa_id'] ?? null,
                'purchase_return_coa_id' => $this->defaultAccountIds['purchase_return_coa_id'] ?? null,
                'unbilled_purchase_coa_id' => $this->defaultAccountIds['unbilled_purchase_coa_id'] ?? null,
                'temporary_procurement_coa_id' => $this->defaultAccountIds['temporary_procurement_coa_id'] ?? null,
            ]
        );
    }

    /**
     * Resolve default chart of account ids used by seeded products.
     *
     * @return array<string, int|null>
     */
    private function resolveDefaultAccountIds(): array
    {
        $mapping = [
            'inventory_coa_id' => '1140.10',
            'sales_coa_id' => '4100.10',
            'sales_return_coa_id' => '4120.10',
            'sales_discount_coa_id' => '4110.10',
            'goods_delivery_coa_id' => '1140.20',
            'cogs_coa_id' => '5100.10',
            'purchase_return_coa_id' => '5120.10',
            'unbilled_purchase_coa_id' => '2100.10',
            'temporary_procurement_coa_id' => '1400.01',
        ];

        $results = [];

        foreach ($mapping as $column => $code) {
            $results[$column] = ChartOfAccount::where('code', $code)->value('id');
        }

        return $results;
    }
}
