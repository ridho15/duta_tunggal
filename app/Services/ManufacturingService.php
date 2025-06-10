<?php

namespace App\Services;

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
}
