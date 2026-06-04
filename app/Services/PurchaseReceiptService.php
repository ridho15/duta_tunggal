<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\JournalCurrencyAmountResolver;
use App\Helpers\MoneyHelper;

class PurchaseReceiptService
{
    /**
     * Lightweight cache so repeated COA lookups by code stay fast.
     *
     * @var array<string, ?ChartOfAccount>
     */
    protected static array $coaCache = [];

    protected function skipWithWarning(string $message, array $context = []): array
    {
        Log::warning('PurchaseReceiptService skipped flow', array_merge([
            'message' => $message,
        ], $context));

        return [
            'status' => 'skipped',
            'message' => $message,
        ];
    }

    /**
     * Resolve the receipt item's unit cost in IDR for ledger and stock valuation.
     *
     * @return array{raw_unit_price: float, currency_id: ?int, currency_code: ?string, exchange_rate: float, unit_price_idr: float}
     */
    protected function resolveReceiptItemUnitCostInIdr(PurchaseReceiptItem $item): array
    {
        return JournalCurrencyAmountResolver::resolvePurchaseReceiptItemUnitCost($item);
    }

    public function generateReceiptNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'RN-' . $date . '-';

        // pick random suffix; retry if collision
        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = PurchaseReceipt::where('receipt_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Post purchase receipt to general ledger.
     */
    public function postPurchaseReceipt(PurchaseReceipt $receipt): array
    {
        // For now, this method just validates the receipt structure
        // Individual item posting happens when items are sent to QC

        $receipt->load([
            'purchaseReceiptItem.purchaseOrderItem',
            'purchaseReceiptItem.product',
            'purchaseReceiptBiaya.coa',
        ]);

        $validItems = 0;
        $debugItems = [];
        foreach ($receipt->purchaseReceiptItem as $item) {
            $qtyAccepted = max(0, $item->qty_accepted ?? 0);
            $unitCost = $this->resolveReceiptItemUnitCostInIdr($item);
            $debugItems[] = [
                'item_id' => $item->id,
                'qtyAccepted' => $qtyAccepted,
                'po_item_id' => $item->purchaseOrderItem?->id ?? null,
                'unitPrice' => $unitCost['unit_price_idr'],
                'rawUnitPrice' => $unitCost['raw_unit_price'],
                'currencyId' => $unitCost['currency_id'],
                'exchangeRate' => $unitCost['exchange_rate'],
            ];

            if ($qtyAccepted > 0 && $unitCost['unit_price_idr'] > 0) {
                $validItems++;
            }
        }

        if ($validItems === 0) {
            return $this->skipWithWarning('No valid items to process', [
                'receipt_id' => $receipt->id,
                'items' => $debugItems,
            ]);
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

    protected function getUnbilledPurchaseFallbackCodes(): array
    {
        return [
            config('coa.unbilled_purchase', '2100.10'),
            '2100.10',
            '2190.10',
            '1180.01',
        ];
    }


    /**
     * Post inventory for purchase receipt item after quality control approval.
     * Creates inventory entry and closes the temporary procurement position.
     */
    public function postItemInventoryAfterQC(PurchaseReceiptItem $item): array
    {
        $item->loadMissing([
            'purchaseOrderItem',
            'product.inventoryCoa',
            'product.unbilledPurchaseCoa',
            'purchaseReceipt.currency',
        ]);

        $qtyAccepted = max(0, $item->qty_accepted ?? 0);
        $poItem = $item->purchaseOrderItem;
        $unitCost = $this->resolveReceiptItemUnitCostInIdr($item);
        $unitPrice = $unitCost['unit_price_idr'];
        $amount = round($qtyAccepted * $unitPrice, 2);

        $qc = $this->resolveCompletedQcForReceiptItem($item);
        [$movementFromType, $movementFromId] = $this->resolveReceiptItemStockSource($item, $qc);

        $journalAlreadyPosted = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $item->id)
            ->where('journal_type', 'inventory')
            ->exists()
        ;

        $existingMovement = $this->findReceiptItemStockMovement($item, $qc);

        if ($journalAlreadyPosted && $existingMovement) {
            $this->syncInventoryStockFromMovements($item);

            return $this->skipWithWarning('Item inventory already posted', [
                'item_id' => $item->id,
            ]);
        }

        if ($qtyAccepted <= 0) {
            return $this->skipWithWarning('No accepted quantity to post inventory', [
                'item_id' => $item->id,
                'qty_accepted' => $qtyAccepted,
            ]);
        }
        if ($unitPrice <= 0) {
            return $this->skipWithWarning('Invalid unit price', [
                'item_id' => $item->id,
                'raw_unit_price' => $unitCost['raw_unit_price'],
                'unit_price_idr' => $unitPrice,
                'currency_id' => $unitCost['currency_id'],
                'exchange_rate' => $unitCost['exchange_rate'],
            ]);
        }

        if ($amount <= 0) {
            return $this->skipWithWarning('Invalid amount', [
                'item_id' => $item->id,
                'amount' => $amount,
            ]);
        }

        $product = $item->product;
        $inventoryCoa = $product->resolveInventoryCoaOrDefault();
        $unbilledPurchaseCoa = $product->resolveUnbilledPurchaseCoaOrDefault();

        if (! $inventoryCoa || ! $unbilledPurchaseCoa) {
            return $this->skipWithWarning('Missing required COA configuration', [
                'item_id' => $item->id,
                'inventory_coa_id' => $inventoryCoa?->id,
                'unbilled_purchase_coa_id' => $unbilledPurchaseCoa?->id,
            ]);
        }
        $date = $item->purchaseReceipt->receipt_date ?? Carbon::now();

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($item);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($item);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($item);

        $receiptRef = $item->purchaseReceipt?->receipt_number ?? ('PRI-' . $item->id);
        $entries = [];
        $amountOriginalCurrency = $unitCost['exchange_rate'] > 0
            ? round($amount / $unitCost['exchange_rate'], 4)
            : round($unitCost['raw_unit_price'] * $qtyAccepted, 4);

        if (! $journalAlreadyPosted) {
            // Debit inventory account
            $entries[] = JournalEntry::create([
                'coa_id' => $inventoryCoa->id,
                'date' => $date,
                'reference' => $receiptRef,
                'description' => 'Debit inventory for receipt item ' . $item->id,
                'debit' => round($amount, 2),
                'credit' => 0,
                'journal_type' => 'inventory',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => PurchaseReceiptItem::class,
                'source_id' => $item->id,
                'currency_id' => $unitCost['currency_id'],
                'exchange_rate' => $unitCost['exchange_rate'],
                'amount_original_currency' => $amountOriginalCurrency,
            ]);

            // Credit unbilled purchase position (goods receipt / GRNI)
            $entries[] = JournalEntry::create([
                'coa_id' => $unbilledPurchaseCoa->id,
                'date' => $date,
                'reference' => $receiptRef,
                'description' => 'Inventory Posting - Credit unbilled purchase for receipt item ' . $item->id,
                'debit' => 0,
                'credit' => round($amount, 2),
                'journal_type' => 'inventory',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => PurchaseReceiptItem::class,
                'source_id' => $item->id,
                'currency_id' => $unitCost['currency_id'],
                'exchange_rate' => $unitCost['exchange_rate'],
                'amount_original_currency' => $amountOriginalCurrency,
            ]);

            Log::info('postItemInventoryAfterQC: created journal entries', ['item_id' => $item->id, 'entries_count' => count($entries)]);

            if (! $this->validateJournalBalance($entries)) {
                Log::info('postItemInventoryAfterQC: journal entries not balanced', ['item_id' => $item->id]);
                return ['status' => 'error', 'message' => 'Journal entries are not balanced'];
            }

            Log::info('postItemInventoryAfterQC: journal entries validated balanced', ['item_id' => $item->id]);
        }
        if (! $existingMovement) {
            $meta = [
                'source' => $qc ? 'quality_control' : 'purchase_receipt',
                'purchase_receipt_id' => $item->purchase_receipt_id,
                'purchase_receipt_item_id' => $item->id,
                'unit_cost' => $unitPrice,
                'unit_cost_idr' => $unitPrice,
                'raw_unit_price' => $unitCost['raw_unit_price'],
                'currency_id' => $unitCost['currency_id'],
                'currency' => $unitCost['currency_code'],
                'exchange_rate' => $unitCost['exchange_rate'],
                'purchase_order_item_id' => $poItem?->id,
                'receipt_number' => $item->purchaseReceipt->receipt_number,
            ];

            if ($qc) {
                $meta['qc_id'] = $qc->id;
                $meta['qc_number'] = $qc->qc_number;
            }

            StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $qtyAccepted,
                'value' => $amount,
                'type' => 'purchase_in',
                'date' => $date,
                'notes' => $qc ? 'Stock inbound from QC completion: ' . $qc->qc_number : 'Stock inbound from QC-approved receipt: ' . $item->purchaseReceipt->receipt_number,
                'meta' => $meta,
                'rak_id' => $item->rak_id ?? null,
                'from_model_type' => $movementFromType,
                'from_model_id' => $movementFromId,
            ]);
        }

        $inventoryStock = $this->syncInventoryStockFromMovements($item);

        return [
            'status' => $journalAlreadyPosted ? 'reconciled' : 'posted',
            'entries' => $entries,
            'inventory_stock_id' => $inventoryStock?->id,
        ];
    }

    public function reconcileReceiptItemStock(PurchaseReceiptItem $item): array
    {
        $item->loadMissing([
            'purchaseOrderItem',
            'product.inventoryCoa',
            'product.unbilledPurchaseCoa',
            'purchaseReceipt.currency',
        ]);

        $qc = $this->resolveCompletedQcForReceiptItem($item);
        [$movementFromType, $movementFromId] = $this->resolveReceiptItemStockSource($item, $qc);
        $existingMovement = $this->findReceiptItemStockMovement($item, $qc);

        if (! $existingMovement) {
            $qtyAccepted = max(0, $item->qty_accepted ?? 0);
            $unitPrice = (float) ($item->purchaseOrderItem?->unit_price ?? 0);
            $amount = round($qtyAccepted * $unitPrice, 2);

            if ($qtyAccepted <= 0 || $unitPrice <= 0 || $amount <= 0) {
                return $this->skipWithWarning('Unable to reconcile stock for receipt item', [
                    'item_id' => $item->id,
                    'qty_accepted' => $qtyAccepted,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ]);
            }

            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($item);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($item);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($item);

            $meta = [
                'source' => $qc ? 'quality_control' : 'purchase_receipt',
                'purchase_receipt_id' => $item->purchase_receipt_id,
                'purchase_receipt_item_id' => $item->id,
                'unit_cost' => $unitPrice,
                'currency' => optional($item->purchaseReceipt->currency)->code,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'receipt_number' => $item->purchaseReceipt->receipt_number,
            ];

            if ($qc) {
                $meta['qc_id'] = $qc->id;
                $meta['qc_number'] = $qc->qc_number;
            }

            StockMovement::create([
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $qtyAccepted,
                'value' => $amount,
                'type' => 'purchase_in',
                'date' => $item->purchaseReceipt->receipt_date ?? now(),
                'notes' => $qc ? 'Stock inbound from QC completion: ' . $qc->qc_number : 'Stock inbound from QC-approved receipt: ' . $item->purchaseReceipt->receipt_number,
                'meta' => $meta,
                'rak_id' => $item->rak_id ?? null,
                'from_model_type' => $movementFromType,
                'from_model_id' => $movementFromId,
            ]);
        }

        $inventoryStock = $this->syncInventoryStockFromMovements($item);

        return [
            'status' => 'reconciled',
            'inventory_stock_id' => $inventoryStock?->id,
        ];
    }

    protected function resolveCompletedQcForReceiptItem(PurchaseReceiptItem $item): ?\App\Models\QualityControl
    {
        return \App\Models\QualityControl::where('from_model_type', \App\Models\PurchaseOrderItem::class)
            ->where('from_model_id', $item->purchase_order_item_id)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->first();
    }

    protected function resolveReceiptItemStockSource(PurchaseReceiptItem $item, ?\App\Models\QualityControl $qc = null): array
    {
        return $qc
            ? [\App\Models\QualityControl::class, $qc->id]
            : [PurchaseReceiptItem::class, $item->id];
    }

    protected function findReceiptItemStockMovement(PurchaseReceiptItem $item, ?\App\Models\QualityControl $qc = null): ?StockMovement
    {
        $candidateSources = [];

        if ($qc) {
            $candidateSources[] = [\App\Models\QualityControl::class, $qc->id];
        }

        $candidateSources[] = [PurchaseReceiptItem::class, $item->id];

        foreach ($candidateSources as [$sourceType, $sourceId]) {
            $movement = StockMovement::where('from_model_type', $sourceType)
                ->where('from_model_id', $sourceId)
                ->first();

            if ($movement) {
                return $movement;
            }
        }

        return null;
    }

    protected function syncInventoryStockFromMovements(PurchaseReceiptItem $item): ?InventoryStock
    {
        $rakId = $item->rak_id ?? null;
        $inTypes = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
        $outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

        $movementQuery = StockMovement::query()
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $item->warehouse_id)
            ->when($rakId !== null, fn ($query) => $query->where('rak_id', $rakId), fn ($query) => $query->whereNull('rak_id'));

        $qtyIn = (float) (clone $movementQuery)->whereIn('type', $inTypes)->sum('quantity');
        $qtyOut = (float) (clone $movementQuery)->whereIn('type', $outTypes)->sum('quantity');
        $computedQty = $qtyIn - $qtyOut;

        $inventoryStock = InventoryStock::firstOrNew([
            'product_id' => $item->product_id,
            'warehouse_id' => $item->warehouse_id,
            'rak_id' => $rakId,
        ]);

        $inventoryStock->qty_available = $computedQty;
        if (! $inventoryStock->exists) {
            $inventoryStock->qty_reserved = 0;
            $inventoryStock->qty_min = $inventoryStock->qty_min ?? 0;
        }
        $inventoryStock->save();

        return $inventoryStock;
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
            return $this->skipWithWarning('Return already posted', [
                'item_id' => $item->id,
            ]);
        }

        $item->loadMissing([
            'purchaseOrderItem',
            'product.purchaseReturnCoa',
            'product.temporaryProcurementCoa',
            'purchaseReceipt.currency'
        ]);

        $qtyAccepted = max(0, $item->qty_accepted ?? 0);
        if ($qtyAccepted <= 0) {
            return $this->skipWithWarning('No accepted quantity to return', [
                'item_id' => $item->id,
                'qty_accepted' => $qtyAccepted,
            ]);
        }

        $poItem = $item->purchaseOrderItem;
        $unitCost = $this->resolveReceiptItemUnitCostInIdr($item);
        $unitPrice = $unitCost['unit_price_idr'];
        if ($unitPrice <= 0) {
            return $this->skipWithWarning('Invalid unit price', [
                'item_id' => $item->id,
                'raw_unit_price' => $unitCost['raw_unit_price'],
                'unit_price_idr' => $unitPrice,
                'currency_id' => $unitCost['currency_id'],
                'exchange_rate' => $unitCost['exchange_rate'],
            ]);
        }

        $amount = round($qtyAccepted * $unitPrice, 2);
        if ($amount <= 0) {
            return $this->skipWithWarning('Invalid amount', [
                'item_id' => $item->id,
                'amount' => $amount,
            ]);
        }

        $product = $item->product;
        $returnCoa = $product->resolvePurchaseReturnCoaOrDefault();
        $temporaryProcurementCoa = $product->resolveTemporaryProcurementCoaOrDefault();

        if (! $returnCoa || ! $temporaryProcurementCoa) {
            return $this->skipWithWarning('Missing required COA configuration', [
                'item_id' => $item->id,
                'return_coa_id' => $returnCoa?->id,
                'temporary_procurement_coa_id' => $temporaryProcurementCoa?->id,
            ]);
        }

        $date = $item->purchaseReceipt->receipt_date ?? Carbon::now()->toDateString();

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($item);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($item);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($item);

        $entries = [];
        $amountOriginalCurrency = $unitCost['exchange_rate'] > 0
            ? round($amount / $unitCost['exchange_rate'], 4)
            : round($unitCost['raw_unit_price'] * $qtyAccepted, 4);

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
            'currency_id' => $unitCost['currency_id'],
            'exchange_rate' => $unitCost['exchange_rate'],
            'amount_original_currency' => $amountOriginalCurrency,
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
            'currency_id' => $unitCost['currency_id'],
            'exchange_rate' => $unitCost['exchange_rate'],
            'amount_original_currency' => $amountOriginalCurrency,
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
        $unbilledCoaForZero = $this->resolveCoaByCodes($this->getUnbilledPurchaseFallbackCodes());
        $coaIdForZero = $unbilledCoaForZero?->id ?? $this->getCoaByCode('1180.01')->id;

        $entries[] = [
            'date' => now(),
            'coa_id' => $coaIdForZero,
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
            return $this->skipWithWarning('Temporary procurement entries already exist', [
                'item_id' => $item->id,
            ]);
        }

        $item->loadMissing([
            'purchaseOrderItem',
            'product.temporaryProcurementCoa',
            'product.unbilledPurchaseCoa',
            'purchaseReceipt.currency'
        ]);

        $qtyAccepted = max(0, $item->qty_accepted ?? 0);
        if ($qtyAccepted <= 0) {
            return $this->skipWithWarning('No accepted quantity', [
                'item_id' => $item->id,
                'qty_accepted' => $qtyAccepted,
            ]);
        }

        $unitCost = $this->resolveReceiptItemUnitCostInIdr($item);
        $unitPrice = $unitCost['unit_price_idr'];
        if ($unitPrice <= 0) {
            return $this->skipWithWarning('Invalid unit price', [
                'item_id' => $item->id,
                'raw_unit_price' => $unitCost['raw_unit_price'],
                'unit_price_idr' => $unitPrice,
                'currency_id' => $unitCost['currency_id'],
                'exchange_rate' => $unitCost['exchange_rate'],
            ]);
        }

        $amount = round($qtyAccepted * $unitPrice, 2);
        if ($amount <= 0) {
            return $this->skipWithWarning('Invalid amount', [
                'item_id' => $item->id,
                'amount' => $amount,
            ]);
        }

        $product = $item->product;
        $temporaryProcurementCoa = $product?->resolveTemporaryProcurementCoaOrDefault();

        if (! $temporaryProcurementCoa) {
            return $this->skipWithWarning('No temporary procurement COA configured for product', [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
            ]);
        }

        // Find unbilled purchase COA from product configuration. If not set on product,
        // prefer liability COA for unbilled purchases created at receipt time
        $unbilledPurchaseCoa = $product?->resolveUnbilledPurchaseCoaOrDefault();
        if (! $unbilledPurchaseCoa) {
            return $this->skipWithWarning('No unbilled purchase COA configured for product and no default liability COA found', [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
            ]);
        }

        $date = $item->purchaseReceipt->receipt_date ?? Carbon::now()->toDateString();

        // Create transaction ID for double-entry bookkeeping
        $transactionId = (string) Str::uuid();

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($item);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($item);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($item);
        $amountOriginalCurrency = $unitCost['exchange_rate'] > 0
            ? round($amount / $unitCost['exchange_rate'], 4)
            : round($unitCost['raw_unit_price'] * $qtyAccepted, 4);

        // Debit temporary procurement position
        $debitEntry = JournalEntry::create([
            'coa_id' => $temporaryProcurementCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Temporary Procurement - Item sent to QC: ' . $product->name . ' (' . $qtyAccepted . ' ' . $product->unit . ')',
            'debit' => round($amount, 2),
            'credit' => 0,
            'journal_type' => 'procurement',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
            'transaction_id' => $transactionId,
            'currency_id' => $unitCost['currency_id'],
            'exchange_rate' => $unitCost['exchange_rate'],
            'amount_original_currency' => $amountOriginalCurrency,
        ]);

        // Credit unbilled purchase liability
        $creditEntry = JournalEntry::create([
            'coa_id' => $unbilledPurchaseCoa->id,
            'date' => $date,
            'reference' => 'PRI-' . $item->id,
            'description' => 'Temporary Procurement - Item sent to QC: ' . $product->name . ' (' . $qtyAccepted . ' ' . $product->unit . ')',
            'debit' => 0,
            'credit' => round($amount, 2),
            'journal_type' => 'procurement',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $item->id,
            'transaction_id' => $transactionId,
            'currency_id' => $unitCost['currency_id'],
            'exchange_rate' => $unitCost['exchange_rate'],
            'amount_original_currency' => $amountOriginalCurrency,
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
        
        Log::info('createAutomaticInvoiceFromReceipt: starting', [
            'receipt_id' => $receipt->id,
            'item_count' => $receipt->purchaseReceiptItem->count(),
            'items' => $receipt->purchaseReceiptItem->map(fn($i) => [
                'id' => $i->id,
                'qty_accepted' => (float) $i->qty_accepted,
                'po_item_id' => $i->purchaseOrderItem?->id,
            ])->toArray(),
        ]);

        foreach ($receipt->purchaseReceiptItem as $receiptItem) {
            if ((float) $receiptItem->qty_accepted > 0) {
                $poItem = $receiptItem->purchaseOrderItem;
                
                    // Normalize unit price to IDR using currency conversion (high-precision)
                    $rawUnitPrice = MoneyHelper::parseHighPrecision($poItem->unit_price ?? $receiptItem->product->cost_price ?? 0);
                    $unitCurrencyId = (int) ($poItem->currency_id ?? $receipt->purchaseOrder?->purchaseOrderCurrency()->first()?->currency_id ?? 0);
                    $unitPrice = \App\Support\CurrencyConversionResolver::convertToIdr($rawUnitPrice, $unitCurrencyId ?: null, false);
                
                $total = round($unitPrice * $receiptItem->qty_accepted, 2);

                // Debug per-item calculation
                Log::info('createAutomaticInvoiceFromReceipt: item calc', [
                    'receipt_item_id' => $receiptItem->id,
                    'po_item_id' => $poItem?->id,
                    'raw_unit_price' => $rawUnitPrice,
                    'unit_currency_id' => $unitCurrencyId,
                    'converted_unit_price' => $unitPrice,
                    'tipe_pajak' => $poItem?->tipe_pajak,
                    'tax_rate' => $poItem?->tax,
                    'qty' => $receiptItem->qty_accepted,
                    'line_gross' => $total,
                ]);

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

        // Calculate tax per item respecting tipe_pajak and per-item tax rate
        $dppTotal = 0.0;
        $taxTotal = 0.0;
        $taxRates = [];
        $acceptedLineCount = 0;
        $taxableLineCount = 0;

        foreach ($receipt->purchaseReceiptItem as $receiptItem) {
            if ((float) $receiptItem->qty_accepted <= 0) {
                continue;
            }

            $acceptedLineCount++;

            $poItem = $receiptItem->purchaseOrderItem;
            
            // Normalize unit price to IDR using currency conversion (high-precision)
            $rawUnitPrice = MoneyHelper::parseHighPrecision($poItem->unit_price ?? $receiptItem->product->cost_price ?? 0);
            $unitCurrencyId = (int) ($poItem->currency_id ?? $receipt->purchaseOrder?->purchaseOrderCurrency()->first()?->currency_id ?? 0);
            $unitPrice = \App\Support\CurrencyConversionResolver::convertToIdr($rawUnitPrice, $unitCurrencyId ?: null, false);
            
            $qty = $receiptItem->qty_accepted;
            $lineGross = round($unitPrice * $qty, 2);
            $rate = (float)($poItem->tax ?? 0);
            $tipe = Str::lower(trim((string) ($poItem->tipe_pajak ?? ($receiptItem->product->tipe_pajak ?? 'Non Pajak'))));

            if (in_array($tipe, ['non pajak', 'non-pajak', 'none', 'non'], true)) {
                $dppLine = $lineGross;
                $taxLine = 0.0;
            } elseif (in_array($tipe, ['eksklusif', 'eklusif', 'exclusive'], true)) {
                // unitPrice is net, tax computed on top
                $dppLine = $lineGross;
                $taxLine = round($dppLine * ($rate / 100), 2);
                $taxableLineCount++;
                $taxRates[] = $rate;
            } else { // Inklusif
                // unitPrice includes tax
                $dppLine = round($lineGross / (1 + ($rate / 100)), 2);
                $taxLine = round($lineGross - $dppLine, 2);
                $taxableLineCount++;
                $taxRates[] = $rate;
            }

            $dppTotal += $dppLine;
            $taxTotal += $taxLine;
        }

        // Include other fees (treated as non-taxable by default)
        foreach ($otherFees as $fee) {
            $dppTotal += $fee['amount'];
        }

        $subtotal = round($dppTotal, 2); // subtotal stored as DPP (net)
        $tax = round($taxTotal, 2);
        $total = round($subtotal + $tax, 2);

        // Determine invoice-wide ppn_rate only when all taxable items share the same rate
        $ppnRate = 0;
        if (!empty($taxRates) && $taxableLineCount === $acceptedLineCount) {
            $uniqueRates = array_values(array_unique($taxRates));
            if (count($uniqueRates) === 1) {
                $ppnRate = $uniqueRates[0];
            }
        }

        $supplier = $receipt->purchaseOrder->supplier ?? null;
        $idrCurrencyId = \App\Support\CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');

        $invoice = \App\Models\Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => \App\Models\PurchaseReceipt::class,
            'from_model_id' => $receipt->id,
            'currency_id' => $idrCurrencyId,
            'exchange_rate' => 1,
            'invoice_date' => now()->toDateString(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'other_fee' => $otherFees, // Add biaya as other_fee
            'total' => $total,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => \App\Models\Invoice::STATUS_PAID, // Create as paid directly
            'ppn_rate' => $ppnRate,
            'dpp' => $subtotal,
            'supplier_name' => $supplier ? $supplier->perusahaan : null,
            'supplier_phone' => $supplier ? $supplier->phone : null,
            'purchase_receipts' => [$receipt->id],
            'cabang_id' => $receipt->cabang_id,
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
            'currency_id' => $idrCurrencyId,
            'exchange_rate' => 1,
            'total_original' => $total,
            'paid_original' => $total,
            'remaining_original' => 0,
            'total' => $total,
            'paid' => $total, // Mark as fully paid since invoice is paid
            'remaining' => 0,
            'due_date' => $invoice->due_date,
            'status' => PaymentStatus::PAID->value,
            'supplier_id' => $supplier ? $supplier->id : null,
        ]);

        // Post journal entries manually since invoice is created as paid
        $ledgerService = new \App\Services\LedgerPostingService();
        $postResult = $ledgerService->postInvoice($invoice);
        Log::info('createAutomaticInvoiceFromReceipt: ledger post result', ['invoice_id' => $invoice->id, 'result' => $postResult]);

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
    public function copyBiayaFromPurchaseOrderToReceipt($purchaseOrder, $receipt)
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
