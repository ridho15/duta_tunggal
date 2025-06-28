<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\QualityControl;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class QualityControlService
{
    public function generateQcNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = QualityControl::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->qc_number, -4));
            $number = $lastNumber + 1;
        }

        return 'QC-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
    public function createQCFromPurchaseReceiptItem($purchaseReceiptItem, $data)
    {
        $qualityControlService = app(QualityControlService::class);
        $qc_number = $qualityControlService->generateQcNumber();
        $qualityControl = QualityControl::where('qc_number', $qc_number)->first();
        while ($qualityControl) {
            $qc_number = $qc_number . '1';
        }
        return $purchaseReceiptItem->qualityControl()->create([
            'qc_number' => $qc_number,
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

            if ($qualityControl->from_model_type == 'App\Models\PurchaseReceiptItem') {
                $qualityControl->purchaseReceiptItem->update([
                    'qty_accepted' => $qualityControl->purchaseReceiptItem->qty_accepted - $qualityControl->rejected_quantity
                ]);
            }
        }

        if ($qualityControl->from_model_type == 'App\Models\Production' && $qualityControl->passed_quantity >= $qualityControl->fromModel->manufacturingOrder->quantity) {
            $qualityControl->fromModel->manufacturingOrder->update([
                'status' => 'completed'
            ]);
            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Manufacturing Completed");
        }

        $qualityControl->update([
            'status' => 1,
            'date_send_stock' => Carbon::now()
        ]);
    }

    public function checkPenerimaanBarang($qualityControl)
    {
        $purchaseOrder = PurchaseOrder::whereHas('purchaseOrderItem', function ($query) use ($qualityControl) {
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
