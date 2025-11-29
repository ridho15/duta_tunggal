<?php

namespace App\Observers;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InventoryStockObserver
{
    /**
     * Handle the InventoryStock "created" event.
     */
    public function created(InventoryStock $inventoryStock): void
    {
        // Calculate inventory value: qty_available * cost_price
        $inventoryValue = $inventoryStock->qty_available * $inventoryStock->product->cost_price;

        if ($inventoryValue <= 0) {
            return; // No need to create journal entry for zero value
        }

        // Find inventory COA (Persediaan)
        $inventoryCoa = ChartOfAccount::where('code', '1-1300')->first();
        if (!$inventoryCoa) {
            // Try alternative codes used in different seeders
            $inventoryCoa = ChartOfAccount::where('code', '1140')->first();
        }
        if (!$inventoryCoa) {
            return; // COA not found, skip
        }

        // Find equity COA (Modal Disetor) for credit side - opening inventory should credit capital, not retained earnings
        $equityCoa = ChartOfAccount::where('code', '3000')->first();
        if (!$equityCoa) {
            // Try alternative codes
            $equityCoa = ChartOfAccount::where('code', '3-1001')->first();
        }
        if (!$equityCoa) {
            // Try another alternative
            $equityCoa = ChartOfAccount::where('code', '3110')->first();
        }
        if (!$equityCoa) {
            return; // No equity COA found
        }

        // If this InventoryStock was created by an operational inbound StockMovement
        // (e.g. purchase_in, transfer_in, manufacture_in, adjustment_in) within
        // a short timeframe, skip creating an opening_balance journal here because
        // the purchase/adjustment flow already created accounting entries.
        $recentInbound = StockMovement::where('product_id', $inventoryStock->product_id)
            ->where('warehouse_id', $inventoryStock->warehouse_id)
            ->whereIn('type', ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'])
            ->where('created_at', '<=', $inventoryStock->created_at)
            ->where('created_at', '>=', Carbon::parse($inventoryStock->created_at)->subSeconds(10))
            ->first();

        if ($recentInbound) {
            // It looks like this inventory record was created as part of an operational
            // stock inbound (e.g. a purchase receipt). Do not create an opening_balance
            // journal to avoid duplicate accounting postings.
            return;
        }

        // Also check for operational outbound movements (manufacture_out, sales, transfer_out, adjustment_out)
        // These are operational transactions and should not create opening balance journals
        $recentOutbound = StockMovement::where('product_id', $inventoryStock->product_id)
            ->where('warehouse_id', $inventoryStock->warehouse_id)
            ->whereIn('type', ['manufacture_out', 'sales', 'transfer_out', 'adjustment_out'])
            ->where('created_at', '<=', $inventoryStock->created_at)
            ->where('created_at', '>=', Carbon::parse($inventoryStock->created_at)->subSeconds(10))
            ->first();

        if ($recentOutbound) {
            // This inventory record was updated as part of an operational stock outbound
            // (e.g. material issue, sales, transfer). Do not create opening_balance journal.
            return;
        }

        // Opening balance journal entries disabled by user request
        // Previously created journal entries for opening inventory balance here
    }

    /**
     * Handle the InventoryStock "updated" event.
     */
    public function updated(InventoryStock $inventoryStock): void
    {
        
    }

    /**
     * Handle the InventoryStock "deleted" event.
     */
    public function deleted(InventoryStock $inventoryStock): void
    {
        $this->deleteJournalEntries($inventoryStock);
    }

    /**
     * Handle the InventoryStock "restored" event.
     */
    public function restored(InventoryStock $inventoryStock): void
    {
        $this->created($inventoryStock);
    }

    /**
     * Handle the InventoryStock "force deleted" event.
     */
    public function forceDeleted(InventoryStock $inventoryStock): void
    {
        $this->deleteJournalEntries($inventoryStock);
    }

    private function deleteJournalEntries(InventoryStock $inventoryStock): void
    {
        JournalEntry::where('source_type', InventoryStock::class)
            ->where('source_id', $inventoryStock->id)
            ->delete();
    }
}
