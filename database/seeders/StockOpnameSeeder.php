<?php

namespace Database\Seeders;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StockOpnameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = Warehouse::all();
        $products = Product::take(15)->get();

        $firstUser = \App\Models\User::first();
        $userId = $firstUser ? $firstUser->id : 1;

        // Create sample stock opnames
        $opnames = [
            [
                'opname_date' => now()->subDays(7),
                'warehouse_id' => $warehouses->first()->id,
                'status' => 'approved',
                'notes' => 'Stock opname bulanan Januari 2025',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now()->subDays(6),
            ],
            [
                'opname_date' => now()->subDays(2),
                'warehouse_id' => $warehouses->first()->id,
                'status' => 'completed',
                'notes' => 'Stock opname mendadak - audit internal',
                'created_by' => $userId,
            ],
            [
                'opname_date' => now(),
                'warehouse_id' => $warehouses->skip(1)->first()?->id ?? $warehouses->first()->id,
                'status' => 'in_progress',
                'notes' => 'Stock opname rutin Februari 2025',
                'created_by' => $userId,
            ],
        ];

        foreach ($opnames as $index => $opnameData) {
            $opnameData['opname_number'] = \App\Models\StockOpname::generateOpnameNumber();
            $opname = StockOpname::create($opnameData);

            // Create opname items
            $selectedProducts = $products->random(min(8, $products->count()));

            foreach ($selectedProducts as $product) {
                // Get system quantity from inventory_stocks
                $inventoryStock = InventoryStock::where('product_id', $product->id)
                    ->where('warehouse_id', $opname->warehouse_id)
                    ->first();

                $systemQty = $inventoryStock ? $inventoryStock->qty_available : rand(10, 100);

                // Simulate physical count (sometimes different from system)
                $variance = rand(-5, 5); // -5 to +5 variance
                $physicalQty = max(0, $systemQty + $variance);

                // Calculate average cost from purchase history
                $averageCost = $this->calculateAverageCostForProduct($product->id, $opname->opname_date);

                $differenceQty = $physicalQty - $systemQty;
                $differenceValue = $differenceQty * $averageCost;
                $totalValue = $physicalQty * $averageCost;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'system_qty' => $systemQty,
                    'physical_qty' => $physicalQty,
                    'difference_qty' => $differenceQty,
                    'unit_cost' => $averageCost, // Use average cost as unit cost
                    'average_cost' => $averageCost,
                    'difference_value' => $differenceValue,
                    'total_value' => $totalValue,
                    'notes' => $variance !== 0 ? 'Selisih ditemukan saat opname' : 'Stock sesuai',
                ]);
            }
        }
    }

    /**
     * Calculate average cost for a product based on purchase history
     */
    private function calculateAverageCostForProduct($productId, $opnameDate)
    {
        // Get all purchase receipts for this product before the opname date
        $purchaseItems = \App\Models\PurchaseReceiptItem::where('product_id', $productId)
            ->whereHas('purchaseReceipt', function($query) use ($opnameDate) {
                $query->where('receipt_date', '<=', $opnameDate);
            })
            ->with('purchaseReceipt')
            ->orderBy('purchase_receipt_items.created_at', 'asc')
            ->get();

        if ($purchaseItems->isEmpty()) {
            // If no purchase history, generate a random cost for demo
            return rand(10000, 500000);
        }

        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($purchaseItems as $item) {
            $quantity = $item->quantity_received ?? $item->quantity ?? 0;
            $unitPrice = $item->unit_price ?? 0;

            $totalQuantity += $quantity;
            $totalValue += ($quantity * $unitPrice);
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : rand(10000, 500000);
    }
}
