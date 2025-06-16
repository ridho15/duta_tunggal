<?php

namespace App\Services;

use App\Models\PurchaseOrder;
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
            $returnProduct = $qualityControl->returnProduct()->create($data);
            $qualityControl->returnProductItem()->create([
                'return_product_id' => $returnProduct->id,
                'product_id' => $qualityControl->product_id,
                'quantity' => $qualityControl->rejected_quantity,
            ]);

            $qualityControl->purchaseReceiptItem->update([
                'qty_accepted' => $qualityControl->purchaseReceiptItem->qty_accepted - $qualityControl->rejected_quantity
            ]);
        }

        $qualityControl->update([
            'status' => 1,
            'date_send_stock' => Carbon::now()
        ]);
    }

    public function checkPenerimaanBarang($qualityControl)
    {
        $purchaseOrder = PurchaseOrder::with(['purchaseOrderItem.purchaseReceiptItem.qualityControl'])->whereHas('purchaseOrderItem', function ($query) use ($qualityControl) {
            $query->whereHas('purchaseReceiptItem', function ($query) use ($qualityControl) {
                $query->whereHas('qualityControl', function ($query) use ($qualityControl) {
                    $query->where('id', $qualityControl->id);
                });
            });
        })->first();

        if ($purchaseOrder) {
        }
    }
}
