<?php

namespace App\Observers;

use App\Models\ManufacturingOrder as ModelsManufacturingOrder;
use App\Models\StockMovement;
use Carbon\Carbon;

class ManufacturingOrder
{
    public function updated(ModelsManufacturingOrder $manufacturingOrder): void
    {
        if ($manufacturingOrder->status == 'in_progress') {
            foreach ($manufacturingOrder->manufacturingOrderMaterial as $manufacturingOrderMaterial) {
                StockMovement::create([
                    'product_id' => $manufacturingOrderMaterial->material_id,
                    'warehouse_id' => $manufacturingOrderMaterial->warehouse_id,
                    'rak_id' => $manufacturingOrderMaterial->rak_id,
                    'quantity' => $manufacturingOrderMaterial->qty_used,
                    'type' => 'manufacture_out',
                    'from_model_type' => 'App\Models\ManufacturingOrder',
                    'from_model_id' => $manufacturingOrder->id,
                    'date' => Carbon::now()
                ]);
            }
        }

        if ($manufacturingOrder->status == 'completed') {
            StockMovement::create([
                'product_id' => $manufacturingOrder->product_id,
                'warehouse_id' => $manufacturingOrder->warehouse_id,
                'rak_id' => $manufacturingOrder->rak_id,
                'quantity' => $manufacturingOrder->quantity,
                'type' => 'manufacture_in',
                'date' => Carbon::now(),
                'from_model_type' => 'App\Models\ManufacturingOrder',
                'from_model_id' => $manufacturingOrder->id,
            ]);
        }
    }
}
