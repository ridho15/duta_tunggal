<?php

namespace App\Services;

use App\Models\QualityControl;
use App\Models\ReturnProduct;
use Carbon\Carbon;

class QualityControlService
{
    public function createQCFromPurchaseReceiptItem($purchaseReceiptItem, $data)
    {
        return QualityControl::create([
            'purchase_receipt_item_id' => $purchaseReceiptItem->id,
            'passed_quantity' => $purchaseReceiptItem->qty_accepted,
            'rejected_quantity' => 0,
            'status' => 0,
            'inspected_by' => $data['inspected_by'],
            'warehouse_id' => $purchaseReceiptItem->warehouse_id,
            'product_id' => $purchaseReceiptItem->product_id,
            'rak_id' => $purchaseReceiptItem->rak_id
        ]);
    }

    public function completeQualityControl($qualityControl, $data)
    {
        $productService = app(ProductService::class);
        if ($qualityControl->passed_quantity > 0) {
            $productService->createStockMovement($qualityControl->product_id, $qualityControl->warehouse_id, $qualityControl->passed_quantity, 'transfer_in', Carbon::now(), $qualityControl->notes, $qualityControl->rak_id, $qualityControl);
        }

        if ($qualityControl->rejected_quantity > 0) {
            $returnProdcut = $qualityControl->returnProduct()->create($data);
            $returnProdcut->returnProductItem()->create([
                'product_id' => $qualityControl->product_id,
                'quantity' => $qualityControl->rejected_quantity,
            ]);
        }

        $qualityControl->update([
            'status' => 1,
            'date_send_stock' => Carbon::now()
        ]);
    }
}
