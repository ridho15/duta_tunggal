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

        if (! $warehouseId) {
            Log::warning('CustomerReturnService: warehouse_id is null — stock restoration will be skipped', [
                'return_id'     => $customerReturn->id,
                'return_number' => $customerReturn->return_number,
            ]);
        }

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

                // For 'repair' items, goods come back for fixing but are NOT immediately
                // returned to saleable stock — they go to a WIP/In-Repair holding account.
                // Journal entries for repair are created separately below.
                if ($item->decision === CustomerReturnItem::DECISION_REPAIR) {
                    $totalRestoredValue += round($qty * $unitCost, 2);
                    // Record stock-in movement so warehouse knows the goods arrived
                    if ($warehouseId) {
                        StockMovement::create([
                            'product_id'      => $item->product_id,
                            'warehouse_id'    => $warehouseId,
                            'quantity'        => $qty,
                            'value'           => round($qty * $unitCost, 2),
                            'type'            => 'customer_return',
                            'reference_id'    => $customerReturn->id,
                            'date'            => $customerReturn->return_date ?? now()->toDateString(),
                            'notes'           => "Retur dari customer (perbaikan): {$customerReturn->return_number}",
                            'from_model_type' => CustomerReturn::class,
                            'from_model_id'   => $customerReturn->id,
                        ]);
                    } else {
                        Log::warning('CustomerReturnService: warehouse_id null, repair stock movement skipped', [
                            'return_id'  => $customerReturn->id,
                            'product_id' => $item->product_id,
                        ]);
                    }
                    continue;
                }

                // ── 1. Restore inventory stock (replace decision) ────────────────────────
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

                // ── 2. Record stock movement (stock IN, replace decision) ──────────────
                if ($warehouseId) {
                    StockMovement::create([
                        'product_id'      => $item->product_id,
                        'warehouse_id'    => $warehouseId,
                        'quantity'        => $qty,
                        'value'           => round($qty * $unitCost, 2),
                        'type'            => 'customer_return',
                        'reference_id'    => $customerReturn->id,
                        'date'            => $customerReturn->return_date ?? now()->toDateString(),
                        'notes'           => "Retur dari customer (penggantian): {$customerReturn->return_number}",
                        'from_model_type' => CustomerReturn::class,
                        'from_model_id'   => $customerReturn->id,
                    ]);
                } else {
                    Log::warning('CustomerReturnService: warehouse_id null, replace stock movement skipped', [
                        'return_id'  => $customerReturn->id,
                        'product_id' => $item->product_id,
                    ]);
                }

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
     * For 'replace' items (goods back to stock):
     *   Debit  Inventory            (1101.01) — goods come back to warehouse
     *   Credit COGS reversal        (5100.10) — cost of those goods is no longer "sold"
     *
     * For 'repair' items (goods in workshop/WIP, not yet back to saleable stock):
     *   Debit  WIP / In-Repair      (1101.02 or fallback to 1101.01) — goods held for repair
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

        // Split total amount into repair vs replace value
        $repairAmount  = 0.0;
        $replaceAmount = 0.0;
        foreach ($customerReturn->customerReturnItems as $item) {
            if ($item->decision === CustomerReturnItem::DECISION_REJECT) {
                continue;
            }
            $itemValue = round((float) $item->quantity * (float) ($item->invoiceItem?->price ?? 0), 2);
            if ($item->decision === CustomerReturnItem::DECISION_REPAIR) {
                $repairAmount += $itemValue;
            } else {
                $replaceAmount += $itemValue;
            }
        }

        // COA: Inventory account (goods back in saleable stock)
        $inventoryCoa = ChartOfAccount::where('code', '1101.01')->first();
        // COA: WIP / In-Repair holding account (goods held for repair)
        $wipCoa       = ChartOfAccount::where('code', '1101.02')->first()
                     ?? $inventoryCoa; // fallback to main inventory if WIP COA not set up yet
        // COA: COGS reversal
        $cogsCoa      = ChartOfAccount::where('code', '5100.10')->first()
                     ?? ChartOfAccount::where('code', '5000')->first();

        if (! $inventoryCoa || ! $cogsCoa) {
            Log::warning('CustomerReturnService: COA account(s) not found — cannot create journal entries', [
                'return_id'    => $customerReturn->id,
                'inventory_ok' => (bool) $inventoryCoa,
                'cogs_ok'      => (bool) $cogsCoa,
            ]);
            throw new \Exception('Akun COA tidak ditemukan untuk jurnal retur customer. Diperlukan: Persediaan (1101.01) dan COGS (5100.10). Silakan hubungi administrator untuk mengkonfigurasi akun tersebut.');
        }

        // Debit Inventory (replace items) – goods physically back in stock
        if ($replaceAmount > 0) {
            JournalEntry::create([
                'coa_id'       => $inventoryCoa->id,
                'date'         => $date,
                'reference'    => $reference,
                'description'  => $desc . ' - Restore inventory value (penggantian)',
                'debit'        => $replaceAmount,
                'credit'       => 0,
                'journal_type' => 'customer_return',
                'source_type'  => CustomerReturn::class,
                'source_id'    => $customerReturn->id,
                'cabang_id'    => $customerReturn->cabang_id,
            ]);
        }

        // Debit WIP (repair items) – goods held for repair, not yet saleable
        if ($repairAmount > 0) {
            JournalEntry::create([
                'coa_id'       => $wipCoa->id,
                'date'         => $date,
                'reference'    => $reference,
                'description'  => $desc . ' - Goods in repair (perbaikan)',
                'debit'        => $repairAmount,
                'credit'       => 0,
                'journal_type' => 'customer_return',
                'source_type'  => CustomerReturn::class,
                'source_id'    => $customerReturn->id,
                'cabang_id'    => $customerReturn->cabang_id,
            ]);
        }

        // Credit COGS reversal – cost of all returned goods is no longer "sold"
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
            'cabang_id'    => $customerReturn->cabang_id,
        ]);

        Log::info('CustomerReturn journal entries created', [
            'return_id'      => $customerReturn->id,
            'repair_amount'  => $repairAmount,
            'replace_amount' => $replaceAmount,
            'total_amount'   => $amount,
        ]);
    }
}
