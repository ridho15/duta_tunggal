<?php

use App\Models\PurchaseOrder;
use App\Models\QualityControl;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('testing', function () {
    $qualityControl = QualityControl::find(6);
    $purchaseOrder = PurchaseOrder::select(['id'])->with(['purchaseOrderItem' => function ($query) {
        $query->select(['id', 'quantity', 'purchase_order_id'])->with(['purchaseReceiptItem' => function ($query) {
            $query->where('is_sent', 1)->select(['id', 'is_sent', 'qty_accepted', 'purchase_order_item_id', 'purchase_receipt_id'])->with(['qualityControl' => function ($query) {
                $query->select(['id', 'purchase_receipt_item_id', 'passed_quantity', 'status'])->where('status', 1);
            }]);
        }]);
    }])->whereHas('purchaseOrderItem', function ($query) use ($qualityControl) {
        $query->whereHas('purchaseReceiptItem', function ($query) use ($qualityControl) {
            $query->where('is_sent', 1)->whereHas('qualityControl', function ($query) use ($qualityControl) {
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

    if ($totalQuantityDibutuhkan == $totalQuantityYangDiterima) {
        $purchaseOrder->update([
            'status' => 'completed',
            'completed_by' => Auth::user()->id,
            'completed_at' => Carbon::now()
        ]);
    }
});
