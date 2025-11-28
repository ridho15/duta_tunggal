<?php

namespace Database\Seeders;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StockAdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = Warehouse::all();
        $products = Product::take(10)->get();

        $firstUser = \App\Models\User::first();
        $userId = $firstUser ? $firstUser->id : 1;

        // Create sample stock adjustments
        $adjustments = [
            [
                'adjustment_date' => now()->subDays(5),
                'warehouse_id' => $warehouses->first()->id,
                'adjustment_type' => 'increase',
                'reason' => 'Penerimaan barang tambahan dari supplier',
                'notes' => 'Barang tambahan yang diterima diluar PO',
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now()->subDays(4),
            ],
            [
                'adjustment_date' => now()->subDays(3),
                'warehouse_id' => $warehouses->first()->id,
                'adjustment_type' => 'decrease',
                'reason' => 'Koreksi stock karena kesalahan input',
                'notes' => 'Stock awal terlalu banyak diinput',
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now()->subDays(2),
            ],
            [
                'adjustment_date' => now()->subDays(1),
                'warehouse_id' => $warehouses->skip(1)->first()?->id ?? $warehouses->first()->id,
                'adjustment_type' => 'increase',
                'reason' => 'Penyesuaian stock berdasarkan audit internal',
                'notes' => 'Ditemukan selisih stock setelah audit',
                'status' => 'draft',
                'created_by' => $userId,
            ],
        ];

        foreach ($adjustments as $index => $adjustmentData) {
            $adjustmentData['adjustment_number'] = \App\Models\StockAdjustment::generateAdjustmentNumber();
            $adjustment = StockAdjustment::create($adjustmentData);

            // Create adjustment items
            $selectedProducts = $products->random(min(3, $products->count()));

            foreach ($selectedProducts as $product) {
                $currentQty = rand(10, 100);
                $adjustmentQty = $adjustment->adjustment_type === 'increase'
                    ? $currentQty + rand(1, 20)
                    : max(0, $currentQty - rand(1, 15));

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $product->id,
                    'current_qty' => $currentQty,
                    'adjusted_qty' => $adjustmentQty,
                    'difference_qty' => $adjustmentQty - $currentQty,
                    'unit_cost' => rand(10000, 500000),
                    'difference_value' => ($adjustmentQty - $currentQty) * rand(10000, 500000),
                    'notes' => 'Auto generated for testing',
                ]);
            }
        }
    }
}
