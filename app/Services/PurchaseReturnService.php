<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnService
{
    public function generateNotaRetur()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $lastPurchaseReturn = PurchaseReturn::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastPurchaseReturn) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastPurchaseReturn->nota_retur, -4));
            $number = $lastNumber + 1;
        }

        return 'NR-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create journal entries for purchase return
     */
    public function createJournalEntry(PurchaseReturn $purchaseReturn): bool
    {
        try {
            DB::transaction(function () use ($purchaseReturn) {
                // Prevent duplicate posting
                if (JournalEntry::where('source_type', PurchaseReturn::class)
                    ->where('source_id', $purchaseReturn->id)
                    ->exists()) {
                    return;
                }

                $totalReturnAmount = $purchaseReturn->purchaseReturnItem->sum(function ($item) {
                    return $item->qty_returned * $item->unit_price;
                });

                if ($totalReturnAmount <= 0) {
                    return;
                }

                $reference = 'PR-' . $purchaseReturn->nota_retur;
                $description = 'Purchase Return: ' . $purchaseReturn->nota_retur;
                $date = $purchaseReturn->return_date ?? now();

                // Get COA accounts
                $inventoryCoa = ChartOfAccount::where('code', '1101.01')->first(); // Inventory account
                $purchaseReturnCoa = ChartOfAccount::where('code', '5120.10')->first(); // Purchase Return account
                $accountsPayableCoa = ChartOfAccount::where('code', '2101.01')->first(); // Accounts Payable

                if (!$inventoryCoa || !$purchaseReturnCoa || !$accountsPayableCoa) {
                    Log::error('Missing COA accounts for purchase return journal', [
                        'inventory' => $inventoryCoa?->id,
                        'purchase_return' => $purchaseReturnCoa?->id,
                        'accounts_payable' => $accountsPayableCoa?->id
                    ]);
                    return;
                }

                $entries = [];

                // Debit Accounts Payable (reduce liability to supplier)
                $entries[] = JournalEntry::create([
                    'coa_id' => $accountsPayableCoa->id,
                    'date' => $date,
                    'reference' => $reference,
                    'description' => $description . ' - Reduce accounts payable',
                    'debit' => $totalReturnAmount,
                    'credit' => 0,
                    'journal_type' => 'purchase_return',
                    'source_type' => PurchaseReturn::class,
                    'source_id' => $purchaseReturn->id,
                ]);

                // Credit Inventory (reduce inventory value)
                $entries[] = JournalEntry::create([
                    'coa_id' => $inventoryCoa->id,
                    'date' => $date,
                    'reference' => $reference,
                    'description' => $description . ' - Reduce inventory value',
                    'debit' => 0,
                    'credit' => $totalReturnAmount,
                    'journal_type' => 'purchase_return',
                    'source_type' => PurchaseReturn::class,
                    'source_id' => $purchaseReturn->id,
                ]);

                Log::info('Purchase return journal entries created', [
                    'purchase_return_id' => $purchaseReturn->id,
                    'nota_retur' => $purchaseReturn->nota_retur,
                    'total_amount' => $totalReturnAmount,
                    'entries_count' => count($entries)
                ]);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create purchase return journal entries', [
                'purchase_return_id' => $purchaseReturn->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Adjust stock for purchase return
     */
    public function adjustStock(PurchaseReturn $purchaseReturn): bool
    {
        try {
            DB::transaction(function () use ($purchaseReturn) {
                foreach ($purchaseReturn->purchaseReturnItem as $item) {
                    // Update inventory stock
                    $inventoryStock = InventoryStock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $purchaseReturn->purchaseReceipt->warehouse_id ?? 1)
                        ->first();

                    if ($inventoryStock) {
                        $inventoryStock->decrement('qty_available', $item->qty_returned);
                        $inventoryStock->decrement('qty_on_hand', $item->qty_returned);
                    }

                    // Create reverse stock movement
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $purchaseReturn->purchaseReceipt->warehouse_id ?? 1,
                        'rak_id' => $item->rak_id,
                        'quantity' => $item->qty_returned,
                        'value' => $item->qty_returned * $item->unit_price,
                        'type' => 'purchase_return',
                        'date' => $purchaseReturn->return_date ?? now(),
                        'notes' => 'Stock outbound from purchase return: ' . $purchaseReturn->nota_retur,
                        'meta' => [
                            'source' => 'purchase_return',
                            'purchase_return_id' => $purchaseReturn->id,
                            'nota_retur' => $purchaseReturn->nota_retur,
                            'unit_price' => $item->unit_price,
                            'reason' => $item->reason,
                        ],
                        'from_model_type' => PurchaseReturn::class,
                        'from_model_id' => $purchaseReturn->id,
                    ]);
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to adjust stock for purchase return', [
                'purchase_return_id' => $purchaseReturn->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process credit note for purchase return
     */
    public function processCreditNote(PurchaseReturn $purchaseReturn, array $data): bool
    {
        try {
            // Update purchase return with credit note info
            $purchaseReturn->update([
                'credit_note_number' => $data['credit_note_number'] ?? null,
                'credit_note_date' => $data['credit_note_date'] ?? now(),
                'credit_note_amount' => $data['credit_note_amount'] ?? $purchaseReturn->purchaseReturnItem->sum(fn($item) => $item->qty_returned * $item->unit_price),
            ]);

            // Update account payable
            $accountPayable = AccountPayable::where('invoice_id', $purchaseReturn->purchaseReceipt->invoice_id ?? null)->first();
            if ($accountPayable) {
                $accountPayable->decrement('remaining', $purchaseReturn->credit_note_amount);
                $accountPayable->increment('paid', $purchaseReturn->credit_note_amount);

                if ($accountPayable->remaining <= 0) {
                    $accountPayable->update(['status' => 'Lunas']);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process credit note', [
                'purchase_return_id' => $purchaseReturn->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process refund for purchase return
     */
    public function processRefund(PurchaseReturn $purchaseReturn, array $data): bool
    {
        try {
            // Update purchase return with refund info
            $purchaseReturn->update([
                'refund_amount' => $data['refund_amount'],
                'refund_date' => $data['refund_date'] ?? now(),
                'refund_method' => $data['refund_method'] ?? 'cash',
            ]);

            // Update account payable
            $accountPayable = AccountPayable::where('invoice_id', $purchaseReturn->purchaseReceipt->invoice_id ?? null)->first();
            if ($accountPayable) {
                $accountPayable->decrement('remaining', $purchaseReturn->refund_amount);
                $accountPayable->increment('paid', $purchaseReturn->refund_amount);

                if ($accountPayable->remaining <= 0) {
                    $accountPayable->update(['status' => 'Lunas']);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'purchase_return_id' => $purchaseReturn->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Submit purchase return for approval
     */
    public function submitForApproval(PurchaseReturn $purchaseReturn): bool
    {
        if ($purchaseReturn->status !== 'draft') {
            throw new \Exception('Only draft purchase returns can be submitted for approval');
        }

        $purchaseReturn->update(['status' => 'pending_approval']);
        return true;
    }

    /**
     * Approve purchase return
     */
    public function approve(PurchaseReturn $purchaseReturn, array $data = []): bool
    {
        if ($purchaseReturn->status !== 'pending_approval') {
            throw new \Exception('Only pending purchase returns can be approved');
        }

        DB::transaction(function () use ($purchaseReturn, $data) {
            $purchaseReturn->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $data['approval_notes'] ?? null,
            ]);

            // Create journal entries
            $this->createJournalEntry($purchaseReturn);

            // Adjust stock
            $this->adjustStock($purchaseReturn);
        });

        return true;
    }

    /**
     * Reject purchase return
     */
    public function reject(PurchaseReturn $purchaseReturn, array $data = []): bool
    {
        if ($purchaseReturn->status !== 'pending_approval') {
            throw new \Exception('Only pending purchase returns can be rejected');
        }

        $purchaseReturn->update([
            'status' => 'rejected',
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_notes' => $data['rejection_notes'] ?? null,
        ]);

        return true;
    }

    /**
     * Create a new purchase return with proper cabang scope
     */
    public function create(array $data): PurchaseReturn
    {
        $data['nota_retur'] = $data['nota_retur'] ?? $this->generateNotaRetur();
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'draft';

        // Set cabang_id from authenticated user or from purchase receipt
        if (!isset($data['cabang_id'])) {
            if (isset($data['purchase_receipt_id'])) {
                $purchaseReceipt = \App\Models\PurchaseReceipt::find($data['purchase_receipt_id']);
                $data['cabang_id'] = $purchaseReceipt?->cabang_id;
            } else {
                $data['cabang_id'] = Auth::user()?->cabang_id;
            }
        }

        return PurchaseReturn::create($data);
    }

    /**
     * Process replacement for purchase return
     */
    public function processReplacement(PurchaseReturn $purchaseReturn, array $data): bool
    {
        try {
            // Update purchase return with replacement info
            $purchaseReturn->update([
                'replacement_po_id' => $data['replacement_po_id'],
                'replacement_date' => $data['replacement_date'] ?? now(),
                'replacement_notes' => $data['replacement_notes'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process replacement', [
                'purchase_return_id' => $purchaseReturn->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update tracking status for purchase return
     */
    public function updateTracking(PurchaseReturn $purchaseReturn, array $data): bool
    {
        try {
            $purchaseReturn->update([
                'supplier_response' => $data['supplier_response'] ?? null,
                'credit_note_received' => $data['credit_note_received'] ?? false,
                'case_closed_date' => $data['case_closed_date'] ?? null,
                'tracking_notes' => $data['tracking_notes'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update tracking', [
                'purchase_return_id' => $purchaseReturn->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Initiate physical return process
     */
    public function initiatePhysicalReturn(PurchaseReturn $purchaseReturn, array $data): bool
    {
        try {
            $purchaseReturn->update([
                'delivery_note' => $data['delivery_note'] ?? null,
                'shipping_details' => $data['shipping_details'] ?? null,
                'physical_return_date' => $data['physical_return_date'] ?? now(),
                'status' => 'physical_return_initiated',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to initiate physical return', [
                'purchase_return_id' => $purchaseReturn->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
