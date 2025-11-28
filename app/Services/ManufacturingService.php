<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\WarehouseConfirmation;

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
        foreach ($manufacturingOrder->manufacturingOrderMaterial as $manufacturingOrderMaterial) {
            $query = InventoryStock::where('product_id', $manufacturingOrderMaterial->material_id);
            if ($manufacturingOrderMaterial->warehouse_id) {
                $query->where('warehouse_id', $manufacturingOrderMaterial->warehouse_id);
            }
            if ($manufacturingOrderMaterial->rak_id) {
                $query->where('rak_id', $manufacturingOrderMaterial->rak_id);
            }
            $inventoryStock = $query->first();

            if (!$inventoryStock) {
                return false;
            }

            // Use qty_required as the required amount; if qty_used is set and higher, validate against that
            $required = max((float) ($manufacturingOrderMaterial->qty_required ?? 0), (float) ($manufacturingOrderMaterial->qty_used ?? 0));
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
     * Update material fulfillment data for a production plan
     */
    public function updateMaterialFulfillment(\App\Models\ProductionPlan $plan): void
    {
        \App\Models\MaterialFulfillment::updateFulfillmentData($plan);
    }

    /**
     * Update material fulfillment data for all production plans
     */
    public function updateAllMaterialFulfillments(): void
    {
        $plans = \App\Models\ProductionPlan::with('billOfMaterial.items')->get();

        foreach ($plans as $plan) {
            $this->updateMaterialFulfillment($plan);
        }
    }
}
