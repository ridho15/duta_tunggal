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

        // Handle asset purchase approval
        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'approved' && $purchaseOrder->is_asset) {
            $this->handleAssetPurchaseApproval($purchaseOrder);
        }

        // Sync related journal entries if total amount changed
        if ($purchaseOrder->wasChanged('total_amount')) {
            $this->syncJournalEntries($purchaseOrder);
        }
    }

    /**
     * Handle asset purchase approval - auto create assets and complete PO
     */
    protected function handleAssetPurchaseApproval(PurchaseOrder $purchaseOrder): void
    {
        // Prevent duplicate asset creation: if assets already exist for this PO, skip
        if (\App\Models\Asset::where('purchase_order_id', $purchaseOrder->id)->exists()) {
            \Illuminate\Support\Facades\Log::warning('Assets already exist for PO, skipping auto-creation', [
                'purchase_order_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
            ]);
            return;
        }

        // Set PO status to completed
        $purchaseOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $purchaseOrder->approved_by,
        ]);

        // Auto-create assets for each item
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            // Compute total per item (for logging); per-unit cost uses unit_price
            $total = \App\Http\Controllers\HelperController::hitungSubtotal(
                (int)$item->quantity,
                (int)$item->unit_price,
                (int)$item->discount,
                (int)$item->tax,
                $item->tipe_pajak
            );

            // Get default COA for assets - adjust codes per your chart of accounts
            $assetCoa = \App\Models\ChartOfAccount::where('code', '1210.01')->first(); // PERALATAN KANTOR
            $accumulatedDepreciationCoa = \App\Models\ChartOfAccount::where('code', '1220.01')->first(); // AKUMULASI PENYUSUTAN
            $depreciationExpenseCoa = \App\Models\ChartOfAccount::where('code', '6311')->first(); // BEBAN PENYUSUTAN

            // Create one asset record per unit purchased
            $units = max(1, (int)$item->quantity);
            for ($i = 0; $i < $units; $i++) {
                $asset = \App\Models\Asset::create([
                    'name' => $item->product->name,
                    'product_id' => $item->product_id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $item->id,
                    'purchase_date' => $purchaseOrder->order_date,
                    'usage_date' => $purchaseOrder->order_date,
                    'purchase_cost' => (int)$item->unit_price,
                    'salvage_value' => 0,
                    'useful_life_years' => 5,
                    'depreciation_method' => 'straight_line',
                    'asset_coa_id' => $assetCoa?->id,
                    'accumulated_depreciation_coa_id' => $accumulatedDepreciationCoa?->id,
                    'depreciation_expense_coa_id' => $depreciationExpenseCoa?->id,
                    'status' => 'active',
                    'notes' => 'Generated from PO ' . $purchaseOrder->po_number,
                    'cabang_id' => $purchaseOrder->cabang_id,
                ]);

                // Calculate depreciation for the created unit
                $asset->calculateDepreciation();
            }
        }

        \Illuminate\Support\Facades\Log::info('Assets auto-created for approved purchase order', [
            'purchase_order_id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
            'items_count' => $purchaseOrder->purchaseOrderItems->count(),
        ]);
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