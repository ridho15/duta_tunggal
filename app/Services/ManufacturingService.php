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
            $inventoryStock = InventoryStock::where(function ($query) use ($manufacturingOrderMaterial) {
                $query->where('warehouse_id', $manufacturingOrderMaterial->warehouse_id)
                    ->orWhere('rak_id', $manufacturingOrderMaterial->rak_id);
            })->where('product_id', $manufacturingOrderMaterial->material_id)->first();
            if (!$inventoryStock) {
                return false;
            }

            if ($inventoryStock->qty_available < $manufacturingOrderMaterial->qty_required) {
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
}
