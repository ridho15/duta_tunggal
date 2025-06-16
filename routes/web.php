<?php

use App\Models\PurchaseOrder;
use App\Models\QualityControl;
use Illuminate\Support\Facades\Route;

Route::get('testing', function () {
    $qualityControl = QualityControl::find(6);
    $purchaseOrder = PurchaseOrder::select(['id'])->with(['purchaseOrderItem' => function ($query) {
        $query->select(['id', 'quantity', 'purchase_order_id'])->with(['purchaseReceiptItem' => function ($query) {
            $query->select(['id', 'qty_accepted', 'purchase_order_item_id', 'purchase_receipt_id'])->with(['qualityControl' => function ($query) {
                $query->select(['id', 'purchase_receipt_item_id', 'passed_quantity']);
            }]);
        }]);
    }])->whereHas('purchaseOrderItem', function ($query) use ($qualityControl) {
        $query->whereHas('purchaseReceiptItem', function ($query) use ($qualityControl) {
            $query->whereHas('qualityControl', function ($query) use ($qualityControl) {
                $query->where('status', 1)->where('id', $qualityControl->id);
            });
        });
    })->first();

    $totalQuantityDibutuhkan = 0;
    $totalQuantityYangDiterima = 0;
    if ($purchaseOrder) {
        foreach ($purchaseOrder->purchaseOrderItem as $purchaseOrderItem) {
            $totalQuantityDibutuhkan += $purchaseOrderItem->quantity;
            foreach ($purchaseOrderItem->purchaseReceiptItem as $purchaseReceiptItem) {
                $totalQuantityYangDiterima += $purchaseReceiptItem->qualityControl->passed_quantity;
            }
        }
    }

    return [
        $purchaseOrder,
        $totalQuantityDibutuhkan,
        $totalQuantityYangDiterima
    ];
});
