<?php

namespace App\Services;

use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Currency;

class PurchaseReceiptService
{
    /**
     * Lightweight cache so repeated COA lookups by code stay fast.
     *
     * @var array<string, ?ChartOfAccount>
     */
    protected static array $coaCache = [];

    public function generateReceiptNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $lastPurchaseReceipt = PurchaseReceipt::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastPurchaseReceipt) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastPurchaseReceipt->receipt_number, -4));
            $number = $lastNumber + 1;
        }

        return 'RN-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Post purchase receipt to general ledger.
     */
    public function postPurchaseReceipt(PurchaseReceipt $receipt): array
    {
        // For now, this method just validates the receipt structure
        // Individual item posting happens when items are sent to QC

        $receipt->loadMissing([
            'purchaseReceiptItem.purchaseOrderItem',
            'purchaseReceiptItem.product',
            'purchaseReceiptBiaya.coa',
        ]);

        $validItems = 0;
        foreach ($receipt->purchaseReceiptItem as $item) {
            $qtyAccepted = max(0, $item->qty_accepted ?? 0);
            if ($qtyAccepted > 0) {
                $poItem = $item->purchaseOrderItem;
                $unitPrice = $poItem?->unit_price ?? 0;
                if ($unitPrice > 0) {
                    $validItems++;
                }
            }
        }

        if ($validItems === 0) {
            return ['status' => 'skipped', 'message' => 'No valid items to process'];
        }

        // Post inventory for each valid item (deferred posting after QC)
        $postedEntries = [];
        foreach ($receipt->purchaseReceiptItem as $item) {
            $qtyAccepted = max(0, $item->qty_accepted ?? 0);
            if ($qtyAccepted <= 0) {
                continue;
            }

            // Attempt to post inventory for each item. The helper contains duplicate checks.
            $result = $this->postItemInventoryAfterQC($item);
            if (isset($result['status']) && $result['status'] === 'posted' && isset($result['entries'])) {
                $postedEntries = array_merge($postedEntries, $result['entries']);
            }
        }

        // Update receipt status to posted
        $this->updateReceiptStatusToPosted($receipt);

        return ['status' => 'posted', 'message' => 'Receipt processed and inventory posted', 'entries' => $postedEntries];
    }

    /**
     * Update receipt status to completed after successful posting
     */
    public function updateReceiptStatusToPosted(PurchaseReceipt $receipt): void
    {
        $receipt->update(['status' => 'completed']);
    }

    /**
     * Validate that journal entries are balanced (debit = credit)
     */
    public function validateJournalBalance(array $entries): bool
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            if ($entry instanceof JournalEntry) {
                $totalDebit += $entry->debit ?? 0;
                $totalCredit += $entry->credit ?? 0;
            } else {
                $totalDebit += $entry['debit'] ?? 0;
                $totalCredit += $entry['credit'] ?? 0;
            }
        }

        return abs($totalDebit - $totalCredit) < 0.01; // allow small floating point differences
    }

    protected function resolveCoaByCodes(array $codes): ?ChartOfAccount
    {
        foreach ($codes as $code) {
            if (! $code) {
                continue;
            }

            if (! array_key_exists($code, self::$coaCache)) {
                self::$coaCache[$code] = ChartOfAccount::where('code', $code)->first();
            }

            if (self::$coaCache[$code]) {
                return self::$coaCache[$code];
            }
        }

        return null;
    }

    protected function getCoaByCode(string $code): ?ChartOfAccount
    {
        if (! array_key_exists($code, self::$coaCache)) {
            self::$coaCache[$code] = ChartOfAccount::where('code', $code)->first();
        }

        return self::$coaCache[$code];
    }


    /**
     * Post inventory for purchase receipt item after quality control approval.
     * Creates inventory entry and closes the temporary procurement position.
     */
    public function postItemInventoryAfterQC(PurchaseReceiptItem $item): array
    {
        // prevent duplicate posting
        if (JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $item->id)
            ->where('description', 'like', '%Inventory Stock%')
            ->exists()
        ) {
            return ['status' => 'skipped', 'message' => 'Item inventory already posted'];
        }

        $item->loadMissing([
            'purchaseOrderItem',
            'product.inventoryCoa',
            'product.temporaryProcurementCoa',
            'product.unbilledPurchaseCoa',
            'purchaseReceipt.currency'
        ]);

        $qtyAccepted = max(0, $item->qty_accepted ?? 0);
        if ($qtyAccepted <= 0) {
            return ['status' => 'skipped', 'message' => 'No accepted quantity to post inventory'];
        }

        $poItem = $item->purchaseOrderItem;
        $unitPrice = $poItem?->unit_price ?? 0;
        if ($unitPrice <= 0) {
            return ['status' => 'skipped', 'message' => 'Invalid unit price'];
        }

        $amount = round($qtyAccepted * $unitPrice, 2);
        if ($amount <= 0) {
            return ['status' => 'skipped', 'message' => 'Invalid amount'];
        }

        $product = $item->product;
        $inventoryCoa = $product->inventoryCoa ?? $this->resolveCoaByCodes(['1140.10', '1140.01']);
        $temporaryProcurementCoa = $product->temporaryProcurementCoa ?? $this->resolveCoaByCodes(['1180.01', '1400.01']);
        $unbilledPurchaseCoa = $product->unbilledPurchaseCoa ?? $this->resolveCoaByCodes(['2100.10', '2190.10', '1180.01']);

        if (! $inventoryCoa || ! $temporaryProcurementCoa || ! $unbilledPurchaseCoa) {
            return ['status' => 'skipped', 'message' => 'Missing required COA configuration'];
        }
        $date = $item->purchaseReceipt->receipt_date ?? Carbon::now()->toDateString();

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($item);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($item);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($item);

        $entries = [];

        // Debit inventory account
        $entries[] = JournalEntry::create([
            'coa_id' => $inventoryCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Debit inventory for receipt item ' . $item->id,
            'debit' => round($amount, 2),
            'credit' => 0,
            'journal_type' => 'inventory',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
        ]);

        // Credit temporary procurement position (close temporary procurement)
        // Use the temporary procurement COA to close the position created when item was sent to QC
        $entries[] = JournalEntry::create([
            'coa_id' => $temporaryProcurementCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Inventory Posting - Credit temporary procurement for receipt item ' . $item->id,
            'debit' => 0,
            'credit' => round($amount, 2),
            'journal_type' => 'inventory',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
        ]);

        if (! $this->validateJournalBalance($entries)) {
            return ['status' => 'error', 'message' => 'Journal entries are not balanced'];
        }

        // Create stock movement so the StockMovementObserver will update inventory quantities.
        // Avoid duplicate stock movements for the same receipt item.
        // Also check if stock movement was already created from QC completion.
        $existingMovement = StockMovement::where('from_model_type', PurchaseReceiptItem::class)
            ->where('from_model_id', $item->id)
            ->orWhere(function ($query) use ($item) {
                $query->where('from_model_type', \App\Models\QualityControl::class)
                    ->whereHas('fromModel', function ($q) use ($item) {
                        $q->where('from_model_type', \App\Models\PurchaseOrderItem::class)
                          ->where('from_model_id', $item->purchase_order_item_id);
                    });
            })
            ->first();

        if (! $existingMovement) {
            $meta = [
                'source' => 'purchase_receipt',
                'purchase_receipt_id' => $item->purchase_receipt_id,
                'purchase_receipt_item_id' => $item->id,
                'unit_cost' => $unitPrice,
                'currency' => optional($item->purchaseReceipt->currency)->code,
                'purchase_order_item_id' => $poItem?->id,
                'receipt_number' => $item->purchaseReceipt->receipt_number,
            ];

            StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $qtyAccepted,
                'value' => $amount,
                'type' => 'purchase_in',
                'date' => $date,
                'notes' => 'Stock inbound from QC-approved receipt: ' . $item->purchaseReceipt->receipt_number,
                'meta' => $meta,
                'rak_id' => $item->rak_id ?? null,
                'from_model_type' => PurchaseReceiptItem::class,
                'from_model_id' => $item->id,
            ]);
        }

        return ['status' => 'posted', 'entries' => $entries];
    }

    /**
     * Post return product for purchase receipt item after stock decision.
     * Creates return entries and reverses temporary procurement.
     */
    public function postReturnProduct(PurchaseReceiptItem $item, string $returnReason = ''): array
    {
        // prevent duplicate posting
        if (JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $item->id)
            ->where('description', 'like', '%Return Product%')
            ->exists()
        ) {
            return ['status' => 'skipped', 'message' => 'Return already posted'];
        }

        $item->loadMissing([
            'purchaseOrderItem',
            'product.purchaseReturnCoa',
            'product.temporaryProcurementCoa',
            'purchaseReceipt.currency'
        ]);

        $qtyAccepted = max(0, $item->qty_accepted ?? 0);
        if ($qtyAccepted <= 0) {
            return ['status' => 'skipped', 'message' => 'No accepted quantity to return'];
        }

        $poItem = $item->purchaseOrderItem;
        $unitPrice = $poItem?->unit_price ?? 0;
        if ($unitPrice <= 0) {
            return ['status' => 'skipped', 'message' => 'Invalid unit price'];
        }

        $amount = round($qtyAccepted * $unitPrice, 2);
        if ($amount <= 0) {
            return ['status' => 'skipped', 'message' => 'Invalid amount'];
        }

        $product = $item->product;
        $returnCoa = $product->purchaseReturnCoa ?? $this->resolveCoaByCodes(['6100.02', '5100.10']); // Return/Expense COA
        $temporaryProcurementCoa = $product->temporaryProcurementCoa ?? $this->resolveCoaByCodes(['1180.01', '1400.01']);

        if (! $returnCoa || ! $temporaryProcurementCoa) {
            return ['status' => 'skipped', 'message' => 'Missing required COA configuration'];
        }

        $date = $item->purchaseReceipt->receipt_date ?? Carbon::now()->toDateString();

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($item);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($item);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($item);

        $entries = [];

        // Debit return/expense account
        $entries[] = JournalEntry::create([
            'coa_id' => $returnCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Return Product - ' . $returnReason . ' for receipt item ' . $item->id,
            'debit' => round($amount, 2),
            'credit' => 0,
            'journal_type' => 'return',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
        ]);

        // Credit temporary procurement position (close temporary procurement)
        $entries[] = JournalEntry::create([
            'coa_id' => $temporaryProcurementCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Return Product - Credit temporary procurement for receipt item ' . $item->id,
            'debit' => 0,
            'credit' => round($amount, 2),
            'journal_type' => 'return',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
        ]);

        if (! $this->validateJournalBalance($entries)) {
            return ['status' => 'error', 'message' => 'Journal entries are not balanced'];
        }

        return ['status' => 'posted', 'entries' => $entries];
    }

    /**
     * Zero out temporary procurement positions when purchase receipt is completed.
     * This reverses the temporary procurement entries created when items were sent to QC.
     */
    public function zeroOutTemporaryProcurementPositions(PurchaseReceipt $receipt): array
    {
        // Get item IDs for this receipt
        $itemIds = $receipt->purchaseReceiptItem()->pluck('id');

        if ($itemIds->isEmpty()) {
            return ['status' => 'success', 'message' => 'No items found for this receipt'];
        }

        // Get all temporary procurement entries for this receipt's items
        $tempEntries = JournalEntry::where('description', 'like', '%Temporary Procurement%')
            ->where('source_type', PurchaseReceiptItem::class)
            ->whereIn('source_id', $itemIds)
            ->where('coa_id', $this->getCoaByCode('1400.01')->id) // Temporary Procurement COA
            ->get();

        if ($tempEntries->isEmpty()) {
            return ['status' => 'success', 'message' => 'No temporary procurement entries to zero out'];
        }

        // Calculate total debit amount from temporary procurement entries
        $totalDebit = $tempEntries->sum('debit');

        if ($totalDebit <= 0) {
            return ['status' => 'success', 'message' => 'No debit amount to zero out'];
        }

        // Create transaction ID for this operation
        $transactionId = Str::uuid();

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($receipt);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($receipt);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($receipt);

        // Create reversing entries:
        // 1. Credit the temporary procurement account (reverse the original debit)
        $entries[] = [
            'date' => now(),
            'coa_id' => $this->getCoaByCode('1400.01')->id, // Temporary Procurement COA
            'debit' => 0,
            'credit' => round($totalDebit, 2),
            'description' => 'Zero out temporary procurement positions - ' . $receipt->receipt_number,
            'transaction_id' => $transactionId,
            'journal_type' => 'inventory',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceipt::class,
            'source_id' => $receipt->id,
            'reference' => 'PR-' . $receipt->id,
        ];

        // 2. Debit the unbilled purchase account (reverse the original credit)
        // Prefer liability COA for unbilled purchases when zeroing out
        $unbilledCoaForZero = $this->resolveCoaByCodes(['2100.10', '2190.10', '1180.01']);
        $coaIdForZero = $unbilledCoaForZero?->id ?? $this->getCoaByCode('1180.01')->id;

        $entries[] = [
            'date' => now(),
            'coa_id' => $coaIdForZero, // Unbilled Purchase COA (prefer 2100.10)
            'debit' => round($totalDebit, 2),
            'credit' => 0,
            'description' => 'Zero out temporary procurement positions - ' . $receipt->receipt_number,
            'transaction_id' => $transactionId,
            'journal_type' => 'inventory',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceipt::class,
            'source_id' => $receipt->id,
            'reference' => 'PR-' . $receipt->id,
        ];

        if (! $this->validateJournalBalance($entries)) {
            return ['status' => 'error', 'message' => 'Journal entries are not balanced'];
        }

        // Post the entries
        foreach ($entries as $entryData) {
            JournalEntry::create($entryData);
        }

        return ['status' => 'posted', 'entries' => $entries];
    }

    /**
     * Create a PurchaseReceipt (and its item) from a completed QualityControl record.
     * Returns the created PurchaseReceiptItem or null on failure.
     *
     * @param \App\Models\QualityControl $qc
     * @return \App\Models\PurchaseReceiptItem|null
     */
    /**
     * Create temporary procurement journal entries for a receipt item created from QC.
     */
    public function createTemporaryProcurementEntriesForReceiptItem(PurchaseReceiptItem $item): array
    {
        // prevent duplicate posting
        if (JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $item->id)
            ->where('description', 'like', '%Temporary Procurement%')
            ->exists()
        ) {
            return ['status' => 'skipped', 'message' => 'Temporary procurement entries already exist'];
        }

        $item->loadMissing([
            'purchaseOrderItem',
            'product.temporaryProcurementCoa',
            'product.unbilledPurchaseCoa',
            'purchaseReceipt.currency'
        ]);

        $qtyAccepted = max(0, $item->qty_accepted ?? 0);
        if ($qtyAccepted <= 0) {
            return ['status' => 'skipped', 'message' => 'No accepted quantity'];
        }

        $poItem = $item->purchaseOrderItem;
        $unitPrice = $poItem?->unit_price ?? 0;
        if ($unitPrice <= 0) {
            return ['status' => 'skipped', 'message' => 'Invalid unit price'];
        }

        $amount = round($qtyAccepted * $unitPrice, 2);
        if ($amount <= 0) {
            return ['status' => 'skipped', 'message' => 'Invalid amount'];
        }

        $product = $item->product;
        $temporaryProcurementCoa = $product?->temporaryProcurementCoa?->exists ? $product->temporaryProcurementCoa : null;

        if (! $temporaryProcurementCoa) {
            return ['status' => 'skipped', 'message' => 'No temporary procurement COA configured for product'];
        }

        // Find unbilled purchase COA from product configuration. If not set on product,
        // prefer liability COA for unbilled purchases created at receipt time
        $unbilledPurchaseCoa = $product?->unbilledPurchaseCoa?->exists ? $product->unbilledPurchaseCoa : $this->resolveCoaByCodes(['2100.10', '2190.10', '1180.01']);
        if (! $unbilledPurchaseCoa) {
            return ['status' => 'skipped', 'message' => 'No unbilled purchase COA configured for product and no default liability COA found'];
        }

        $date = $item->purchaseReceipt->receipt_date ?? Carbon::now()->toDateString();

        // Create transaction ID for double-entry bookkeeping
        $transactionId = (string) Str::uuid();

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($item);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($item);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($item);

        // Debit temporary procurement position
        $debitEntry = JournalEntry::create([
            'coa_id' => $temporaryProcurementCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Temporary Procurement Entry - From QC: ' . $product->name . ' (' . $qtyAccepted . ' ' . $product->unit . ')',
            'debit' => round($amount, 2),
            'credit' => 0,
            'journal_type' => 'procurement',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
            'transaction_id' => $transactionId,
        ]);

        // Credit unbilled purchase liability
        $creditEntry = JournalEntry::create([
            'coa_id' => $unbilledPurchaseCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Temporary Procurement Entry - From QC: ' . $product->name . ' (' . $qtyAccepted . ' ' . $product->unit . ')',
            'debit' => 0,
            'credit' => round($amount, 2),
            'journal_type' => 'procurement',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
            'transaction_id' => $transactionId,
        ]);

        return ['status' => 'posted', 'entries' => [$debitEntry, $creditEntry]];
    }

    /**
     * Process automatic stock movement for items created from completed QC.
     * This is called when receipt is created from QC that has already been approved.
     */
    /**
     * Process stock movement for items that have QC from PurchaseOrderItem.
     * This is called when receipt is created for pre-QC items.
     */
    public function processStockMovementForPreQcItems(PurchaseReceipt $receipt): array
    {
        $receipt->loadMissing([
            'purchaseReceiptItem.purchaseOrderItem.qualityControl',
            'purchaseReceiptItem.product'
        ]);

        $processedItems = 0;
        $entries = [];

        foreach ($receipt->purchaseReceiptItem as $receiptItem) {
            $poItem = $receiptItem->purchaseOrderItem;

            // Check if this PO item has QC from PurchaseOrderItem (pre-receipt QC)
            if ($poItem && $poItem->qualityControl && $poItem->qualityControl->from_model_type === \App\Models\PurchaseOrderItem::class) {
                $qc = $poItem->qualityControl;

                // Only process if QC is completed and item is accepted
                if ($qc->status == 1 && $receiptItem->qty_accepted > 0) {
                    $productService = app(\App\Services\ProductService::class);

                    $unitCost = $poItem->unit_price ?? (float) ($receiptItem->product->cost_price ?? 0);
                    $value = round($unitCost * $receiptItem->qty_accepted, 2);

                    $meta = [
                        'source' => 'purchase_receipt',
                        'purchase_receipt_id' => $receipt->id,
                        'purchase_receipt_item_id' => $receiptItem->id,
                        'unit_cost' => $unitCost,
                        'currency' => optional($poItem->currency)->code,
                        'purchase_order_item_id' => $poItem->id,
                        'receipt_number' => $receipt->receipt_number,
                        'qc_number' => $qc->qc_number,
                    ];

                    $productService->createStockMovement(
                        $receiptItem->product_id,
                        $receiptItem->warehouse_id,
                        $receiptItem->qty_accepted,
                        'purchase_in',
                        Carbon::now(),
                        'Stock inbound after QC approval: ' . $qc->qc_number,
                        $receiptItem->rak_id,
                        $qc, // Reference to QC
                        $value,
                        $meta
                    );

                    $processedItems++;
                }
            }
        }

        return [
            'status' => 'processed',
            'message' => "Processed stock movement for {$processedItems} pre-QC items",
            'processed_items' => $processedItems
        ];
    }

    /**
     * Create automatic invoice for purchase receipt after stock movement is completed.
     * This is called when stock movement is processed successfully.
     */
    public function createAutomaticInvoiceFromReceipt(PurchaseReceipt $receipt): array
    {
        $receipt->loadMissing([
            'purchaseReceiptItem.purchaseOrderItem',
            'purchaseReceiptItem.product',
            'purchaseOrder',
            'purchaseReceiptBiaya.currency'
        ]);

        // Check if invoice already exists for this receipt
        $existingInvoice = \App\Models\Invoice::where('from_model_type', \App\Models\PurchaseReceipt::class)
            ->where('from_model_id', $receipt->id)
            ->first();

        if ($existingInvoice) {
            return [
                'status' => 'skipped',
                'message' => 'Invoice already exists for this receipt',
                'invoice_id' => $existingInvoice->id
            ];
        }

        $invoiceService = app(\App\Services\InvoiceService::class);
        $subtotal = 0;
        $invoiceItems = [];

        foreach ($receipt->purchaseReceiptItem as $receiptItem) {
            if ($receiptItem->qty_accepted > 0) {
                $poItem = $receiptItem->purchaseOrderItem;
                $unitPrice = $poItem->unit_price ?? (float) ($receiptItem->product->cost_price ?? 0);
                $total = round($unitPrice * $receiptItem->qty_accepted, 2);

                $invoiceItems[] = [
                    'product_id' => $receiptItem->product_id,
                    'quantity' => $receiptItem->qty_accepted,
                    'price' => $unitPrice,
                    'total' => $total,
                ];

                $subtotal += $total;
            }
        }

        // Add biaya lainnya that should be included in invoice
        $otherFees = [];
        \Illuminate\Support\Facades\Log::info("Processing biaya for receipt {$receipt->id}, biaya count: " . $receipt->purchaseReceiptBiaya->count());
        foreach ($receipt->purchaseReceiptBiaya as $biaya) {
            \Illuminate\Support\Facades\Log::info("Checking biaya: {$biaya->nama_biaya}, masuk_invoice: {$biaya->masuk_invoice}");
            if ($biaya->masuk_invoice == 1) { // Only include biaya that should go to invoice
                \Illuminate\Support\Facades\Log::info("Including biaya: {$biaya->nama_biaya} in invoice");
                try {
                    $biayaTotal = round($biaya->total * ($biaya->currency ? $biaya->currency->to_rupiah : 1), 2);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Error calculating biaya total: " . $e->getMessage());
                    $biayaTotal = round($biaya->total, 2); // Fallback to total without currency conversion
                }

                // Add to other_fees array instead of invoice items
                $otherFees[] = [
                    'name' => $biaya->nama_biaya,
                    'amount' => $biayaTotal,
                ];

                $subtotal += $biayaTotal;
            }
        }

        if (empty($invoiceItems)) {
            return [
                'status' => 'skipped',
                'message' => 'No accepted items to invoice'
            ];
        }

        // Calculate tax (PPN 11%)
        $ppnRate = 11;
        $dpp = round($subtotal / (1 + ($ppnRate / 100)), 2);
        $tax = round($subtotal - $dpp, 2);
        $total = round($subtotal + $tax, 2);

        $supplier = $receipt->purchaseOrder->supplier ?? null;

        $invoice = \App\Models\Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => \App\Models\PurchaseReceipt::class,
            'from_model_id' => $receipt->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'other_fee' => $otherFees, // Add biaya as other_fee
            'total' => $total,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'paid', // Create as paid directly
            'ppn_rate' => $ppnRate,
            'dpp' => $dpp,
            'supplier_name' => $supplier ? $supplier->name : null,
            'supplier_phone' => $supplier ? $supplier->phone : null,
            'purchase_receipts' => [$receipt->id],
        ]);

        // Create invoice items
        foreach ($invoiceItems as $itemData) {
            \App\Models\InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'price' => $itemData['price'],
                'total' => $itemData['total'],
            ]);
        }

        // Create account payable
        \App\Models\AccountPayable::create([
            'invoice_id' => $invoice->id,
            'total' => $total,
            'paid' => $total, // Mark as fully paid since invoice is paid
            'remaining' => 0,
            'due_date' => $invoice->due_date,
            'status' => 'Lunas',
            'supplier_id' => $supplier ? $supplier->id : null,
        ]);

        // Post journal entries manually since invoice is created as paid
        $ledgerService = new \App\Services\LedgerPostingService();
        $ledgerService->postInvoice($invoice);

        return [
            'status' => 'created',
            'message' => 'Invoice created automatically',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number
        ];
    }

    /**
     * Copy biaya tambahan from Purchase Order to Purchase Receipt
     */
    protected function copyBiayaFromPurchaseOrderToReceipt($purchaseOrder, $receipt)
    {
        // Load biaya relationship if not already loaded
        if (!$purchaseOrder->relationLoaded('purchaseOrderBiaya')) {
            $purchaseOrder->load('purchaseOrderBiaya');
        }

        // Get all biaya from purchase order
        $poBiayas = $purchaseOrder->purchaseOrderBiaya;

        if (!$poBiayas || $poBiayas->isEmpty()) {
            return; // No biaya to copy
        }

        foreach ($poBiayas as $poBiaya) {
            \App\Models\PurchaseReceiptBiaya::create([
                'purchase_receipt_id' => $receipt->id,
                'currency_id' => $poBiaya->currency_id,
                'coa_id' => $poBiaya->coa_id,
                'nama_biaya' => $poBiaya->nama_biaya,
                'total' => $poBiaya->total,
                'untuk_pembelian' => $poBiaya->untuk_pembelian,
                'masuk_invoice' => $poBiaya->masuk_invoice,
                'purchase_order_biaya_id' => $poBiaya->id,
            ]);
        }

        Log::info('Copied ' . $poBiayas->count() . ' biaya items from PO ' . $purchaseOrder->po_number . ' to receipt ' . $receipt->receipt_number);
    }
}
