<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;

class PurchaseOrderObserver
{
    protected $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    /**
     * Handle the PurchaseOrder "saved" event.
     * Run the total calculation after the model is persisted to avoid
     * interfering with form validation and relation syncing (Filament nested
     * relationship data may not be available during the "saving" event).
     */
    public function saved(PurchaseOrder $purchaseOrder): void
    {
        // Skip if this is already being called from updateTotalAmount to prevent infinite loop
        if (\App\Services\PurchaseOrderService::isUpdatingTotalAmount()) {
            return;
        }

        // Update total amount after the purchase order has been saved
        $this->purchaseOrderService->updateTotalAmount($purchaseOrder);
    }

    /**
     * Handle the PurchaseOrder "created" event.
     */
    public function created(PurchaseOrder $purchaseOrder): void
    {
        // Update total amount when purchase order is first created
        $this->purchaseOrderService->updateTotalAmount($purchaseOrder);
    }

    /**
     * Handle the PurchaseOrder "updated" event.
     */
    public function updated(PurchaseOrder $purchaseOrder): void
    {
        // Update total amount when purchase order is updated
        $this->purchaseOrderService->updateTotalAmount($purchaseOrder);

        // Sync related journal entries if total amount changed
        if ($purchaseOrder->wasChanged('total_amount')) {
            $this->syncJournalEntries($purchaseOrder);
        }
    }

    /**
     * Sync journal entries when purchase order amounts change
     */
    protected function syncJournalEntries(PurchaseOrder $purchaseOrder): void
    {
        $journalEntries = $purchaseOrder->journalEntries()
            ->where('journal_type', 'purchase')
            ->get();

        if ($journalEntries->isEmpty()) {
            return;
        }

        $reference = 'PO-' . $purchaseOrder->po_number;
        $description = 'Purchase Order: ' . $purchaseOrder->po_number;

        foreach ($journalEntries as $entry) {
            // Only update if the entry is directly linked to the PO
            // (not through invoice, which should have its own sync logic)
            if ($entry->source_type === 'App\\Models\\PurchaseOrder') {
                $updates = [
                    'reference' => $reference,
                    'description' => $description,
                    'date' => $purchaseOrder->order_date,
                ];

                // Update debit amount if this is a simple debit entry (no credit)
                if ($entry->debit > 0 && $entry->credit == 0) {
                    $updates['debit'] = $purchaseOrder->total_amount;
                }

                $entry->update($updates);
            }
        }

        \Illuminate\Support\Facades\Log::info('PurchaseOrder journal entries synced', [
            'purchase_order_id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
            'entries_updated' => $journalEntries->count(),
        ]);
    }

    /**
     * Public method to sync journal entries (can be called from other observers)
     */
    public function syncJournalEntriesPublic(PurchaseOrder $purchaseOrder): void
    {
        $this->syncJournalEntries($purchaseOrder);
    }
}