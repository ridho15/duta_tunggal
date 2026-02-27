<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\QualityControl;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnService
{
    public function generateNotaRetur()
    {
        $date = now()->format('Ymd');
        $prefix = 'NR-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = PurchaseReturn::where('nota_retur', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Create a PurchaseReturn automatically from a completed QC that has rejected items.
     *
     * @param  QualityControl  $qc           The completed QC record (from_model_type = PurchaseOrderItem)
     * @param  string          $action       One of PurchaseReturn::QC_ACTION_* constants
     * @param  int|null        $mergePoId    Target PO id when action = merge_next_order
     * @return PurchaseReturn
     * @throws \Exception
     */
    public function createFromQualityControl(QualityControl $qc, string $action, ?int $mergePoId = null): PurchaseReturn
    {
        if ($qc->rejected_quantity <= 0) {
            throw new \Exception('Cannot create purchase return: QC has no rejected items.');
        }

        $validActions = [
            PurchaseReturn::QC_ACTION_REDUCE_STOCK,
            PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY,
            PurchaseReturn::QC_ACTION_MERGE_NEXT_ORDER,
        ];
        if (!in_array($action, $validActions, true)) {
            throw new \Exception("Invalid failed_qc_action: {$action}");
        }

        if ($action === PurchaseReturn::QC_ACTION_MERGE_NEXT_ORDER && !$mergePoId) {
            throw new \Exception('merge_next_order requires a target purchase order (merge_target_po_id).');
        }

        /** @var PurchaseOrderItem $poItem */
        $poItem = $qc->fromModel;
        if (!$poItem || !$poItem->purchaseOrder) {
            throw new \Exception('Cannot locate PurchaseOrderItem or its PurchaseOrder for this QC.');
        }

        $purchaseOrder = $poItem->purchaseOrder;
        $unitPrice     = $poItem->unit_price;

        return DB::transaction(function () use ($qc, $action, $mergePoId, $poItem, $purchaseOrder, $unitPrice) {
            $purchaseReturn = PurchaseReturn::create([
                'quality_control_id' => $qc->id,
                'purchase_receipt_id' => null,          // receipt not yet created at QC stage
                'failed_qc_action'   => $action,
                'replacement_po_id'  => $action === PurchaseReturn::QC_ACTION_MERGE_NEXT_ORDER ? $mergePoId : null,
                'nota_retur'         => $this->generateNotaRetur(),
                'return_date'        => now(),
                'created_by'         => Auth::id(),
                'status'             => 'draft',
                'cabang_id'          => $purchaseOrder->cabang_id ?? Auth::user()?->cabang_id,
                'notes'              => $this->buildQcReturnNotes($qc, $action),
            ]);

            // Create one return item for the rejected product
            PurchaseReturnItem::create([
                'purchase_return_id'      => $purchaseReturn->id,
                'purchase_receipt_item_id' => null,     // no receipt item yet
                'product_id'             => $qc->product_id,
                'qty_returned'           => $qc->rejected_quantity,
                'unit_price'             => $unitPrice,  // original PO price preserved
                'reason'                 => $qc->reason_reject ?? 'Rejected in QC: ' . $qc->qc_number,
            ]);

            // Mark QC purchase return as processed
            $qc->update(['purchase_return_processed' => now()]);

            Log::info('PurchaseReturn created from QC', [
                'purchase_return_id' => $purchaseReturn->id,
                'qc_id'              => $qc->id,
                'action'             => $action,
                'rejected_qty'       => $qc->rejected_quantity,
                'unit_price'         => $unitPrice,
            ]);

            return $purchaseReturn;
        });
    }

    private function buildQcReturnNotes(QualityControl $qc, string $action): string
    {
        $labels = PurchaseReturn::qcActionOptions();
        $actionLabel = $labels[$action] ?? $action;
        return "Retur otomatis dari QC #{$qc->qc_number}. Tindakan: {$actionLabel}. "
             . "Qty ditolak: {$qc->rejected_quantity}. Alasan: " . ($qc->reason_reject ?? '-');
    }

    /**
     * Execute the QC resolution action when a QC-based return is approved.
     *
     * reduce_stock       → decrement PO item quantity by the returned qty
     * wait_next_delivery → update tracking notes; PO remains open so supplier resends
     * merge_next_order   → create a new PO item on the target PO carrying the original price
     */
    public function executeQcResolution(PurchaseReturn $purchaseReturn): void
    {
        if (!$purchaseReturn->isQcReturn()) {
            return;
        }

        $action = $purchaseReturn->failed_qc_action;
        $qc     = $purchaseReturn->qualityControl;

        if (!$qc || !$qc->fromModel) {
            Log::warning('executeQcResolution: QC or fromModel not found', ['return_id' => $purchaseReturn->id]);
            return;
        }

        /** @var PurchaseOrderItem $poItem */
        $poItem = $qc->fromModel;

        switch ($action) {
            case PurchaseReturn::QC_ACTION_REDUCE_STOCK:
                $this->resolveByReducingPoQty($purchaseReturn, $poItem);
                break;

            case PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY:
                $this->resolveByWaitingNextDelivery($purchaseReturn, $poItem);
                break;

            case PurchaseReturn::QC_ACTION_MERGE_NEXT_ORDER:
                $this->resolveByMergingNextOrder($purchaseReturn, $poItem);
                break;
        }
    }

    /**
     * Option A – Reduce PO item qty so the order reflects actual received amount.
     */
    private function resolveByReducingPoQty(PurchaseReturn $purchaseReturn, PurchaseOrderItem $poItem): void
    {
        $totalRejected = $purchaseReturn->purchaseReturnItem->sum('qty_returned');

        // Clamp so qty doesn't go negative
        $newQty = max(0, $poItem->quantity - $totalRejected);
        $poItem->update(['quantity' => $newQty]);

        $purchaseReturn->update([
            'tracking_notes' => ($purchaseReturn->tracking_notes ?? '')
                . "\n[Approved] PO item qty reduced from {$poItem->quantity} to {$newQty}.",
        ]);

        Log::info('QC return resolved: reduce_stock', [
            'return_id'   => $purchaseReturn->id,
            'po_item_id'  => $poItem->id,
            'old_qty'     => $poItem->quantity,
            'new_qty'     => $newQty,
            'rejected_qty'=> $totalRejected,
        ]);
    }

    /**
     * Option B – Flag the return as pending supplier resend; PO stays open.
     */
    private function resolveByWaitingNextDelivery(PurchaseReturn $purchaseReturn, PurchaseOrderItem $poItem): void
    {
        $purchaseReturn->update([
            'supplier_response' => 'pending_resend',
            'tracking_notes'    => ($purchaseReturn->tracking_notes ?? '')
                . "\n[Approved] Waiting for supplier to resend " . $purchaseReturn->purchaseReturnItem->sum('qty_returned') . " unit(s).",
        ]);

        // Keep PO open so future deliveries can be received
        Log::info('QC return resolved: wait_next_delivery', [
            'return_id'  => $purchaseReturn->id,
            'po_item_id' => $poItem->id,
        ]);
    }

    /**
     * Option C – Add a new line item to the target PO carrying the original unit price.
     */
    private function resolveByMergingNextOrder(PurchaseReturn $purchaseReturn, PurchaseOrderItem $originalPoItem): void
    {
        $targetPoId    = $purchaseReturn->replacement_po_id;
        $totalRejected = $purchaseReturn->purchaseReturnItem->sum('qty_returned');
        $originalPrice = $originalPoItem->unit_price;

        if (!$targetPoId) {
            Log::warning('resolveByMergingNextOrder: no replacement_po_id set', ['return_id' => $purchaseReturn->id]);
            return;
        }

        // Create a new PO item on the target PO with the original price
        PurchaseOrderItem::create([
            'purchase_order_id'    => $targetPoId,
            'product_id'           => $originalPoItem->product_id,
            'quantity'             => $totalRejected,
            'unit_price'           => $originalPrice,    // original price inherited
            'uom_id'               => $originalPoItem->uom_id,
            'notes'                => "Merged from rejected QC #{$purchaseReturn->qualityControl?->qc_number} "
                                     . "(original PO item #{$originalPoItem->id}, price IDR {$originalPrice})",
            'refer_item_model_type' => get_class($originalPoItem),
            'refer_item_model_id'  => $originalPoItem->id,
        ]);

        $purchaseReturn->update([
            'replacement_notes' => ($purchaseReturn->replacement_notes ?? '')
                . "\n[Approved] {$totalRejected} unit(s) merged into PO #{$targetPoId} at original price IDR {$originalPrice}.",
        ]);

        Log::info('QC return resolved: merge_next_order', [
            'return_id'    => $purchaseReturn->id,
            'target_po_id' => $targetPoId,
            'qty'          => $totalRejected,
            'unit_price'   => $originalPrice,
        ]);
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

            if ($purchaseReturn->isQcReturn()) {
                // QC-based return: items were rejected before entering stock.
                // Execute the chosen resolution (reduce PO qty / wait / merge) instead of
                // the standard inventory reversal + journal.
                $this->executeQcResolution($purchaseReturn);
            } else {
                // Standard receipt-based return: reverse inventory and create journal entries.
                $this->createJournalEntry($purchaseReturn);
                $this->adjustStock($purchaseReturn);
            }
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
