<?php

namespace App\Observers;

use App\Models\QualityControl;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseOrderItem;

class QualityControlObserver
{
    /**
     * Handle the QualityControl "saving" event.
     * This validates passed_quantity and total_inspected before create or update operations.
     */
    public function saving(QualityControl $qualityControl): void
    {
        // Skip validation if this is a completion operation (status change to completed)
        // The completion validation is handled in QualityControlService::completeQualityControl
        if ($qualityControl->status == 1) {
            return;
        }

        // Validate based on the source model type
        if ($qualityControl->from_model_type === PurchaseReceiptItem::class && $qualityControl->from_model_id) {
            $item = PurchaseReceiptItem::find($qualityControl->from_model_id);
            if ($item) {
                // Always use qty_accepted if available, otherwise qty_received
                $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;

                // Validate passed_quantity individually
                if ($qualityControl->passed_quantity > $maxInspectable) {
                    throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) cannot exceed accepted quantity ({$maxInspectable}) in purchase receipt.");
                }

                // Validate total inspected (passed + rejected)
                $totalInspected = $qualityControl->passed_quantity + $qualityControl->rejected_quantity;
                if ($totalInspected > $maxInspectable) {
                    throw new \Exception("QC total inspected quantity ({$totalInspected}) cannot exceed accepted quantity ({$maxInspectable}) in purchase receipt.");
                }
            }
        } elseif ($qualityControl->from_model_type === PurchaseOrderItem::class && $qualityControl->from_model_id) {
            $item = PurchaseOrderItem::find($qualityControl->from_model_id);
            if ($item) {
                // Validate passed_quantity individually
                if ($qualityControl->passed_quantity > $item->quantity) {
                    throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) cannot exceed ordered quantity ({$item->quantity}) in purchase order.");
                }

                // Validate total inspected (passed + rejected)
                $totalInspected = $qualityControl->passed_quantity + $qualityControl->rejected_quantity;
                if ($totalInspected > $item->quantity) {
                    throw new \Exception("QC total inspected quantity ({$totalInspected}) cannot exceed ordered quantity ({$item->quantity}) in purchase order.");
                }
            }
        }
    }

    /**
     * Handle the QualityControl "created" event.
     */
    public function created(QualityControl $qualityControl): void
    {
        // Update PurchaseReceiptItem is_sent status when QC is created
        if ($qualityControl->from_model_type === PurchaseReceiptItem::class) {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem) {
                $purchaseReceiptItem->update(['is_sent' => 1]);
            }
        }
    }

    /**
     * Handle the QualityControl "deleting" event.
     */
    public function deleting(QualityControl $qualityControl): void
    {
        // Cascade delete related stock movement and return product
        $qualityControl->stockMovement()->delete();
        $qualityControl->returnProduct()->delete();

        // Revert PurchaseReceiptItem is_sent status when QC is deleted
        if ($qualityControl->from_model_type === PurchaseReceiptItem::class) {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem) {
                $purchaseReceiptItem->update(['is_sent' => 0]);
            }
        }
    }
}