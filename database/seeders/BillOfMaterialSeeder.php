<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Product;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BillOfMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::with('uom')->get();

        if ($products->count() < 2) {
            $this->command?->warn('BillOfMaterialSeeder skipped: not enough products available.');
            return;
        }

        // Get default COA for manufacturing
        $finishedGoodsCoa = ChartOfAccount::where('code', '1140.03')->first(); // Persediaan Barang Jadi
        $workInProgressCoa = ChartOfAccount::where('code', '1140.02')->first(); // Persediaan Barang dalam Proses

        if (!$finishedGoodsCoa || !$workInProgressCoa) {
            $this->command?->warn('BillOfMaterialSeeder skipped: Manufacturing COA not found. Please run ManufacturingCoaSeeder first.');
            return;
        }

        $finishedGoods = $products->whereNotNull('uom_id')->take(5);
        $bomCount = 0;
        $itemCount = 0;

        foreach ($finishedGoods as $product) {
            $product->forceFill([
                'is_manufacture' => true,
                'is_active' => true,
            ])->save();

            $components = $this->pickComponents($products, $product);

            if ($components->isEmpty()) {
                continue;
            }

            $laborCost = 50000;
            $overheadCost = 25000;

            $bom = BillOfMaterial::updateOrCreate(
                ['code' => 'BOM-' . $product->sku],
                [
                    'cabang_id' => $product->cabang_id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'nama_bom' => 'BOM ' . $product->name,
                    'note' => 'Seeded automatically',
                    'is_active' => true,
                    'uom_id' => $product->uom_id,
                    'labor_cost' => $laborCost,
                    'overhead_cost' => $overheadCost,
                    'total_cost' => 0,
                    'finished_goods_coa_id' => $finishedGoodsCoa->id,
                    'work_in_progress_coa_id' => $workInProgressCoa->id,
                ]
            );

            $componentIds = [];
            foreach ($components as $component) {
                $component->forceFill([
                    'is_raw_material' => true,
                    'is_active' => true,
                ])->save();

                $quantity = max(1, random_int(1, 3));
                $unitPrice = (float) ($component->cost_price ?? 0);
                $subtotal = $quantity * $unitPrice;

                BillOfMaterialItem::updateOrCreate(
                    [
                        'bill_of_material_id' => $bom->id,
                        'product_id' => $component->id,
                    ],
                    [
                        'quantity' => $quantity,
                        'uom_id' => $component->uom_id,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'note' => 'Component ' . $component->sku,
                    ]
                );

                $componentIds[] = $component->id;
                $itemCount++;
            }

            BillOfMaterialItem::where('bill_of_material_id', $bom->id)
                ->whereNotIn('product_id', $componentIds)
                ->delete();

            $materialCost = BillOfMaterialItem::where('bill_of_material_id', $bom->id)->sum('subtotal');
            $bom->forceFill([
                'labor_cost' => $laborCost,
                'overhead_cost' => $overheadCost,
                'total_cost' => $materialCost + $laborCost + $overheadCost,
            ])->save();

            $bomCount++;
        }

        $this->command?->info("Seeded {$bomCount} bill of materials with {$itemCount} components.");
        $this->command?->info("Default COA set: Finished Goods ({$finishedGoodsCoa->code}) and Work in Progress ({$workInProgressCoa->code})");
    }

    private function pickComponents(Collection $products, Product $finishedGood): Collection
    {
        return $products
            ->where('id', '!=', $finishedGood->id)
            ->shuffle()
            ->take(3)
            ->values();
    }
}
