<?php

namespace App\Observers;

use App\Models\QualityControl;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseOrderItem;

class QualityControlObserver
{

    public function created(QualityControl $qualityControl): void
    {
       if($qualityControl->from_model_type === PurchaseReceiptItem::class) {
            // Revert PurchaseReceiptItem is_sent status when QC is updated
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem) {
                $purchaseReceiptItem->update(['is_sent' => 1]);
            }
        }
    }
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
     * Handle the QualityControl "updated" event.
     */
    public function updated(QualityControl $qualityControl): void
    {
        if ($qualityControl->wasChanged('passed_quantity')) {
            $this->syncJournalEntries($qualityControl);
        }

        // Sync return product items if rejected_quantity changed
        if ($qualityControl->wasChanged('rejected_quantity')) {
            $this->syncReturnProductItems($qualityControl);
        }
    }

    /**
     * Sync journal entries when QC data changes
     */
    protected function syncJournalEntries(QualityControl $qualityControl): void
    {
        // Only sync if QC is completed and has journal entries
        if ($qualityControl->status != 1) {
            return;
        }

        $journalEntries = $qualityControl->journalEntries;
        if ($journalEntries->isEmpty()) {
            return;
        }

        // Get new amount based on updated passed_quantity
        $fromModel = $qualityControl->fromModel;
        $passedQuantity = $qualityControl->passed_quantity;

        // Get unit price based on model type
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $unitPrice = $fromModel?->unit_price ?? 0;
        } elseif ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $unitPrice = $fromModel?->purchaseOrderItem?->unit_price ?? 0;
        } else {
            $unitPrice = 0;
        }

        if ($passedQuantity <= 0 || $unitPrice <= 0) {
            return;
        }

        $newAmount = round($passedQuantity * $unitPrice, 2);

        // Update all journal entries with new amount
        foreach ($journalEntries as $entry) {
            if ($entry->debit > 0) {
                $entry->debit = $newAmount;
            } elseif ($entry->credit > 0) {
                $entry->credit = $newAmount;
            }
            $entry->save();
        }

        // Also update stock movement value if exists
        $stockMovement = $qualityControl->stockMovement;
        if ($stockMovement) {
            $stockMovement->value = $newAmount;
            $stockMovement->quantity = $passedQuantity;
            $stockMovement->save();
        }
    }

    /**
     * Sync return product items when QC rejected_quantity changes
     */
    protected function syncReturnProductItems(QualityControl $qualityControl): void
    {
        // Only sync if QC is completed and has return product
        if ($qualityControl->status != 1) {
            return;
        }

        $returnProduct = $qualityControl->returnProduct;
        if (!$returnProduct) {
            return;
        }

        $returnProductItem = $returnProduct->returnProductItems()->first();
        if ($returnProductItem) {
            $returnProductItem->quantity = $qualityControl->rejected_quantity;
            $returnProductItem->save();
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

        // Cascade delete related journal entries
        $qualityControl->journalEntries()->delete();

        // Revert PurchaseReceiptItem is_sent status when QC is deleted
        if ($qualityControl->from_model_type === PurchaseReceiptItem::class) {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem) {
                $purchaseReceiptItem->update(['is_sent' => 0]);
            }
        }
    }

    /**
     * Handle the QualityControl "restored" event.
     */
    public function restored(QualityControl $qualityControl): void
    {
        // Revert PurchaseReceiptItem is_sent status when QC is restored
        if ($qualityControl->from_model_type === PurchaseReceiptItem::class) {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem) {
                $purchaseReceiptItem->update(['is_sent' => 1]);
            }
        }

        // Note: Journal entries and stock movements should be recreated by the service
        // that handles QC completion, not automatically restored here
        // This prevents duplicate entries if QC is restored multiple times
    }
}