<?php

namespace App\Services;

use App\Models\StockMovement;

class ProductService
{
    public function createStockMovement($product_id, $warehouse_id, $quantity, $type, $date, $notes, $rak_id, $fromModel)
    {
        if ($fromModel) {
            return $fromModel->stockMovement()->create([
                'product_id' => $product_id,
                'warehouse_id' => $warehouse_id,
                'quantity' => $quantity,
                'type' => $type,
                'date' => $date,
                'notes' => $notes,
                'rak_id' => $rak_id
            ]);
        }
        return StockMovement::create([
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => $quantity,
            'type' => $type,
            'date' => $date,
            'notes' => $notes,
            'rak_id' => $rak_id
        ]);
    }
}
