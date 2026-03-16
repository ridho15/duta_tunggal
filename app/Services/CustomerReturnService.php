<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerReturnService
{
    /**
     * Process the completion of a customer return.
     *
     * Mirrors the QC reject flow for purchase returns:
     *  - For items with decision = 'repair' or 'replace': goods are physically back at DT,
     *    so inventory (qty_available) is restored and a stock-in movement is recorded.
     *  - For items with decision = 'reject': the claim was rejected, goods stay with the
     *    customer (or are scrapped), so no stock is added.
     * - Journal entries are created once for the whole return.
     * - stock_restored_at is set to prevent double-processing.
     *
     * @throws \Exception if the return is already processed or has no warehouse set
     */
    public function processCompletion(CustomerReturn $customerReturn): void
    {
        if ($customerReturn->stock_restored_at) {
            throw new \Exception("Customer return #{$customerReturn->return_number} has already been processed.");
        }

        $warehouseId = $customerReturn->warehouse_id;

        DB::transaction(function () use ($customerReturn, $warehouseId) {
            $totalRestoredValue = 0;

            $customerReturn->loadMissing('customerReturnItems.invoiceItem');

            foreach ($customerReturn->customerReturnItems as $item) {
                // Only restore stock for items that are physically returned to DT
                // repair  = goods come back for fixing, then dispatch again
                // replace = goods come back, customer gets new unit from stock
                // reject  = claim rejected, goods do NOT come back
                if ($item->decision === CustomerReturnItem::DECISION_REJECT) {
                    continue;
                }

                $qty      = (float) $item->quantity;
                $unitCost = (float) ($item->invoiceItem?->price ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                // ── 1. Restore inventory stock ────────────────────────
                if ($warehouseId) {
                    $stock = InventoryStock::firstOrNew([
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $warehouseId,
                    ]);

                    if ($stock->exists) {
                        $stock->increment('qty_available', $qty);
                    } else {
                        $stock->qty_available = $qty;
                        $stock->qty_reserved  = 0;
                        $stock->qty_min       = 0;
                        $stock->save();
                    }
                }

                // ── 2. Record stock movement (stock IN) ──────────────
                StockMovement::create([
                    'product_id'      => $item->product_id,
                    'warehouse_id'    => $warehouseId,
                    'quantity'        => $qty,
                    'value'           => round($qty * $unitCost, 2),
                    'type'            => 'customer_return',
                    'reference_id'    => $customerReturn->id,
                    'date'            => $customerReturn->return_date ?? now()->toDateString(),
                    'notes'           => "Retur dari customer: {$customerReturn->return_number} — "
                                       . ($item->decision === CustomerReturnItem::DECISION_REPAIR
                                           ? 'Perbaikan'
                                           : 'Penggantian'),
                    'from_model_type' => CustomerReturn::class,
                    'from_model_id'   => $customerReturn->id,
                ]);

                $totalRestoredValue += round($qty * $unitCost, 2);
            }

            // ── 3. Create journal entries ─────────────────────────────
            if ($totalRestoredValue > 0) {
                $this->createJournalEntries($customerReturn, $totalRestoredValue);
            }

            // ── 4. Mark as processed ──────────────────────────────────
            $customerReturn->update([
                'status'             => CustomerReturn::STATUS_COMPLETED,
                'stock_restored_at'  => now(),
                'completed_at'       => now(),
            ]);

            Log::info('CustomerReturn processed', [
                'return_id'     => $customerReturn->id,
                'return_number' => $customerReturn->return_number,
                'warehouse_id'  => $warehouseId,
                'total_value'   => $totalRestoredValue,
            ]);
        });
    }

    /**
     * Create journal entries for a completed customer return.
     *
     * When goods are returned by a customer the accounting effect (mirroring QC reject) is:
     *   Debit  Inventory            (1101.01) — goods come back to warehouse
     *   Credit COGS reversal        (5100.10) — cost of those goods is no longer "sold"
     */
    private function createJournalEntries(CustomerReturn $customerReturn, float $amount): void
    {
        // Prevent duplicate posting
        if (JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->exists()) {
            return;
        }

        $date      = ($customerReturn->completed_at ?? now())->toDateString();
        $reference = $customerReturn->return_number;
        $desc      = "Customer Return: {$reference}";

        // COA: Inventory account (goods back in stock)
        $inventoryCoa = ChartOfAccount::where('code', '1101.01')->first();
        // COA: COGS reversal (the goods were previously debited to COGS when sold)
        $cogsCoa      = ChartOfAccount::where('code', '5100.10')->first()
                     ?? ChartOfAccount::where('code', '5000')->first();

        if (! $inventoryCoa || ! $cogsCoa) {
            Log::warning('CustomerReturnService: COA account(s) not found — skipping journal entries', [
                'return_id'    => $customerReturn->id,
                'inventory_ok' => (bool) $inventoryCoa,
                'cogs_ok'      => (bool) $cogsCoa,
            ]);
            return;
        }

        // Debit Inventory – goods physically back at warehouse
        JournalEntry::create([
            'coa_id'       => $inventoryCoa->id,
            'date'         => $date,
            'reference'    => $reference,
            'description'  => $desc . ' - Restore inventory value',
            'debit'        => $amount,
            'credit'       => 0,
            'journal_type' => 'customer_return',
            'source_type'  => CustomerReturn::class,
            'source_id'    => $customerReturn->id,
        ]);

        // Credit COGS reversal – cost of goods is no longer "sold"
        JournalEntry::create([
            'coa_id'       => $cogsCoa->id,
            'date'         => $date,
            'reference'    => $reference,
            'description'  => $desc . ' - COGS reversal',
            'debit'        => 0,
            'credit'       => $amount,
            'journal_type' => 'customer_return',
            'source_type'  => CustomerReturn::class,
            'source_id'    => $customerReturn->id,
        ]);

        Log::info('CustomerReturn journal entries created', [
            'return_id' => $customerReturn->id,
            'amount'    => $amount,
        ]);
    }
}
