<?php

namespace App\Services;

use App\Models\QualityControl;

class QualityControlService
{
    public function createQCFromPurchaseReceiptItem($purchaseReceiptItem)
    {
        return QualityControl::create([
            'purchase_receipt_item_id' => $purchaseReceiptItem->id,
            'passed_quantity' => $purchaseReceiptItem->qty_accepted,
            'rejected_quantity' => 0,
            'status' => 0,
            'warehouse_id' => $purchaseReceiptItem->warehouse_id,
            'product_id' => $purchaseReceiptItem->product_id,
            'rak_id' => $purchaseReceiptItem->rak_id
        ]);
    }
}
