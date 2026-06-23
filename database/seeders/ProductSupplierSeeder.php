<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::query()->select(['id', 'sku', 'cost_price'])->get();
        $suppliers = Supplier::query()->select(['id', 'code'])->get();

        if ($products->isEmpty() || $suppliers->isEmpty()) {
            $this->command?->warn('Skipping ProductSupplierSeeder: products or suppliers are missing.');

            return;
        }

        $now = now();
        $attachedCount = 0;

        foreach ($products as $product) {
            $sampleSize = min(3, $suppliers->count());
            $selectedSuppliers = $suppliers->shuffle()->take($sampleSize)->values();

            foreach ($selectedSuppliers as $supplier) {
                $supplierPrice = $this->makeSupplierPrice((float) $product->cost_price);
                $existing = DB::table('product_supplier')
                    ->where('product_id', $product->id)
                    ->where('supplier_id', $supplier->id)
                    ->exists();

                $payload = [
                    'supplier_price' => $supplierPrice,
                    'updated_at' => $now,
                ];

                if ($existing) {
                    DB::table('product_supplier')
                        ->where('product_id', $product->id)
                        ->where('supplier_id', $supplier->id)
                        ->update($payload);
                } else {
                    DB::table('product_supplier')->insert([
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'supplier_price' => $supplierPrice,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $attachedCount++;
            }
        }

        $this->command?->info("Seeded {$attachedCount} product-supplier relations successfully!");
    }

    private function makeSupplierPrice(float $costPrice): float
    {
        if ($costPrice <= 0) {
            return (float) random_int(1000, 100000);
        }

        $markupFactor = random_int(85, 98) / 100;

        return round($costPrice * $markupFactor, 2);
    }
}