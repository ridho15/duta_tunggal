<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\QualityControl;
use App\Models\ReturnProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

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
            if ($totalQuantityDibutuhkan == $totalQuantityYangDiterima) {
                $purchaseOrder->update([
                    'status' => 'completed',
                    'completed_by' => Auth::user()->id,
                    'completed_at' => Carbon::now()
                ]);

                HelperController::sendNotification(isSuccess: true, message: 'Purchase Order Completed', title: 'Information');
            }
        }
    }
}
