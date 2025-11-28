<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\WarehouseConfirmation;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\ProductionPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ManufacturingService
{
    public function createWarehouseConfirmation($manufacturingOrder)
    {
        return WarehouseConfirmation::create([
            'manufacturing_order_id' => $manufacturingOrder->id,
            'status' => 'request',
            'notes' => null,
            'confirmed_by' => null,
            'confirmed_at' => null
        ]);
    }

    public function checkStockMaterial($manufacturingOrder)
    {
        $items = $manufacturingOrder->items ?? [];
        foreach ($items as $item) {
            $query = InventoryStock::where('product_id', $item['product_id']);
            if (!empty($item['warehouse_id'])) {
                $query->where('warehouse_id', $item['warehouse_id']);
            }
            if (!empty($item['rak_id'])) {
                $query->where('rak_id', $item['rak_id']);
            }
            $inventoryStock = $query->first();

            if (!$inventoryStock) {
                return false;
            }

            // Use quantity as the required amount
            $required = (float) ($item['quantity'] ?? 0);
            if ($inventoryStock->qty_available < $required) {
                return false;
            }
        }

        return true;
    }

    public function generateMoNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = ManufacturingOrder::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->mo_number, -4));
            $number = $lastNumber + 1;
        }

        return 'MO-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function generateIssueNumber(string $type = 'issue'): string
    {
        $prefix = $type === 'issue' ? 'MI' : 'MR'; // Material Issue / Material Return
        $date = now()->format('Ymd');
        
        $lastIssue = \App\Models\MaterialIssue::where('issue_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('issue_number', 'desc')
            ->first();

        if ($lastIssue) {
            $lastNumber = (int) substr($lastIssue->issue_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $newNumber);
    }

    /**
     * Create MaterialIssue automatically for a ProductionPlan
     */
    public function createMaterialIssueForProductionPlan(ProductionPlan $productionPlan): ?MaterialIssue
    {
        try {
            // Check if BOM exists
            if (!$productionPlan->billOfMaterial) {
                Log::warning("ProductionPlan {$productionPlan->id} has no BOM, skipping MaterialIssue creation");
                return null;
            }

            // Check if MaterialIssue already exists for this ProductionPlan
            $existingIssue = MaterialIssue::where('production_plan_id', $productionPlan->id)
                ->where('type', 'issue')
                ->first();

            if ($existingIssue) {
                Log::info("MaterialIssue already exists for ProductionPlan {$productionPlan->id}");
                return $existingIssue;
            }

            // Validate stock availability before creating MaterialIssue
            $stockValidation = $this->validateStockForProductionPlan($productionPlan);
            if (!$stockValidation['valid']) {
                Log::warning("Cannot create MaterialIssue for ProductionPlan {$productionPlan->id}: " . $stockValidation['message']);
                throw new \Exception($stockValidation['message']);
            }

            // Create MaterialIssue
            $materialIssue = MaterialIssue::create([
                'issue_number' => $this->generateIssueNumber('issue'),
                'production_plan_id' => $productionPlan->id,
                'warehouse_id' => $productionPlan->warehouse_id,
                'issue_date' => now()->toDateString(),
                'type' => 'issue',
                'status' => 'draft',
                'total_cost' => 0,
                'notes' => 'Auto-generated from Production Plan scheduling',
                'created_by' => Auth::id() ?? 1, // Default to admin if no auth
            ]);

            // Create MaterialIssueItems from BOM
            $totalCost = 0;
            foreach ($productionPlan->billOfMaterial->items as $bomItem) {
                $quantity = $bomItem->quantity * $productionPlan->quantity;
                $costPerUnit = $bomItem->product->cost_price ?? 0;
                $itemTotalCost = $quantity * $costPerUnit;

                MaterialIssueItem::create([
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $bomItem->product_id,
                    'uom_id' => $bomItem->uom_id,
                    'warehouse_id' => $productionPlan->warehouse_id,
                    'quantity' => $quantity,
                    'cost_per_unit' => $costPerUnit,
                    'total_cost' => $itemTotalCost,
                    'status' => 'draft',
                    'inventory_coa_id' => $productionPlan->billOfMaterial->work_in_progress_coa_id,
                ]);

                $totalCost += $itemTotalCost;
            }

            // Update total cost
            $materialIssue->update(['total_cost' => $totalCost]);

            Log::info("MaterialIssue {$materialIssue->issue_number} created for ProductionPlan {$productionPlan->id}");

            return $materialIssue;

        } catch (\Exception $e) {
            Log::error("Failed to create MaterialIssue for ProductionPlan {$productionPlan->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate stock availability for ProductionPlan BOM items
     */
    protected function validateStockForProductionPlan(ProductionPlan $productionPlan): array
    {
        if (!$productionPlan->billOfMaterial) {
            return ['valid' => true, 'message' => 'No BOM to validate'];
        }

        $insufficientStock = [];
        $outOfStock = [];

        foreach ($productionPlan->billOfMaterial->items as $bomItem) {
            $requiredQty = $bomItem->quantity * $productionPlan->quantity;

            $inventoryStock = InventoryStock::where('product_id', $bomItem->product_id)
                ->where('warehouse_id', $productionPlan->warehouse_id)
                ->first();

            $availableQty = $inventoryStock ? $inventoryStock->qty_available : 0;

            if ($availableQty <= 0) {
                $outOfStock[] = "{$bomItem->product->name} (Stock: 0)";
            } elseif ($availableQty < $requiredQty) {
                $insufficientStock[] = "{$bomItem->product->name} (Dibutuhkan: {$requiredQty}, Tersedia: {$availableQty})";
            }
        }

        if (!empty($outOfStock)) {
            return [
                'valid' => false,
                'message' => 'Stock habis untuk produk berikut: ' . implode(', ', $outOfStock)
            ];
        }

        if (!empty($insufficientStock)) {
            return [
                'valid' => false,
                'message' => 'Stock tidak mencukupi untuk produk berikut: ' . implode(', ', $insufficientStock)
            ];
        }

        return [
            'valid' => true,
            'message' => 'Stock tersedia untuk semua item'
        ];
    }
}
