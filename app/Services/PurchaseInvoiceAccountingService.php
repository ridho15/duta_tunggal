<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Helpers\MoneyHelper;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Support\CurrencyConversionResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceAccountingService
{
    private static bool $deferPosting = false;

    public static function isPostingDeferred(): bool
    {
        return self::$deferPosting;
    }

    public static function withoutObserverPosting(callable $callback): mixed
    {
        $previous = self::$deferPosting;
        self::$deferPosting = true;

        try {
            return $callback();
        } finally {
            self::$deferPosting = $previous;
        }
    }

    public function normalizeFormData(array $data): array
    {
        $receiptBacked = $this->expectedReceiptBackedInvoiceDataFromReceiptIds(
            $data['purchase_receipts'] ?? $data['selected_purchase_receipts'] ?? []
        );

        if ($receiptBacked !== null) {
            $data['invoiceItem'] = $receiptBacked['invoice_items'];

            if (! empty($receiptBacked['purchase_order_ids'])) {
                $data['purchase_order_ids'] = $receiptBacked['purchase_order_ids'];
                $data['selected_purchase_orders'] = $receiptBacked['purchase_order_ids'];
            }

            if (! empty($receiptBacked['from_model_id'])) {
                $data['from_model_type'] = PurchaseOrder::class;
                $data['from_model_id'] = $receiptBacked['from_model_id'];
            }

            if (! empty($receiptBacked['cabang_id'])) {
                $data['cabang_id'] = $receiptBacked['cabang_id'];
            }

            if (! empty($receiptBacked['currency_id'])) {
                $data['currency_id'] = $receiptBacked['currency_id'];
                $data['exchange_rate'] = $receiptBacked['exchange_rate'] ?? 1.0;
            }

            if (! empty($receiptBacked['receipt_fees']) && empty($data['receiptBiayaItems'])) {
                $data['receiptBiayaItems'] = $receiptBacked['receipt_fees'];
            }
        } else {
            $poCurrencyContext = $this->currencyContextFromPurchaseOrderIds(
                $data['purchase_order_ids'] ?? $data['selected_purchase_orders'] ?? (! empty($data['from_model_id']) && ($data['from_model_type'] ?? null) === PurchaseOrder::class ? [$data['from_model_id']] : [])
            );

            if ($poCurrencyContext !== null) {
                $data['currency_id'] = $poCurrencyContext['currency_id'];
                $data['exchange_rate'] = $poCurrencyContext['exchange_rate'];
            }
        }

        $data['invoiceItem'] = $this->normalizeInvoiceItems($data['invoiceItem'] ?? []);

        $data['subtotal'] = $this->resolveSubtotal($data);
        $data['dpp'] = $data['subtotal'];

        $taxSummary = $this->resolveTaxSummaryFromFormData($data);
        $data['invoiceItem'] = $taxSummary['items'];
        $data['tax'] = $taxSummary['effective_rate'];
        $data['ppn_rate'] = $taxSummary['effective_rate'];
        $data['ppn_amount'] = $taxSummary['tax_amount'];

        $data['pph22_amount'] = (float) MoneyHelper::safeParse($data['pph22_amount'] ?? 0);
        $data['bea_masuk_amount'] = (float) MoneyHelper::safeParse($data['bea_masuk_amount'] ?? 0);

        $data['other_fee'] = $this->normalizeOtherFees($data);

        $otherFeeTotal = (float) collect($data['other_fee'])->sum(fn (array $fee) => (float) ($fee['amount'] ?? 0));
        $data['total'] = round(
            (float) $data['subtotal']
            + (float) $data['ppn_amount']
            + $otherFeeTotal
            + (float) $data['pph22_amount']
            + (float) $data['bea_masuk_amount'],
            2
        );

        $data['cabang_id'] = $this->resolveSourceCabangId($data) ?? ($data['cabang_id'] ?? null);

        return $data;
    }

    public function expectedReceiptBackedInvoiceData(Invoice $invoice): ?array
    {
        return $this->expectedReceiptBackedInvoiceDataFromReceiptIds($invoice->purchase_receipts ?? []);
    }

    public function expectedReceiptBackedInvoiceDataFromReceiptIds($receiptIds): ?array
    {
        $receiptIds = collect($receiptIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($receiptIds->isEmpty()) {
            return null;
        }

        $receipts = PurchaseReceipt::withoutGlobalScopes()
            ->with([
                'purchaseOrder.purchaseOrderItem.currency',
                'purchaseOrder.purchaseOrderCurrency.currency',
                'purchaseReceiptItem.purchaseOrderItem.currency',
                'purchaseReceiptBiaya.currency',
            ])
            ->whereIn('id', $receiptIds)
            ->get();

        if ($receipts->isEmpty()) {
            return null;
        }

        $items = [];
        $receiptFees = [];
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $currencySnapshots = collect();

        foreach ($receipts as $receipt) {
            $purchaseOrder = $receipt->purchaseOrder;

            foreach ($receipt->purchaseReceiptItem as $receiptItem) {
                $purchaseOrderItem = $this->resolvePurchaseOrderItemForReceiptItem($receiptItem, $purchaseOrder);

                if (! $purchaseOrderItem || ! $purchaseOrderItem->exists) {
                    continue;
                }

                $quantity = (float) ($receiptItem->qty_accepted ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $line = $this->buildReceiptBackedInvoiceItem($receiptItem, $purchaseOrderItem, $quantity);
                $items[] = $line;
                $subtotal += (float) $line['total'];
                $taxAmount += (float) $line['tax_amount'];

                $currencySnapshots->push($this->resolvePurchaseOrderItemCurrencySnapshot($purchaseOrderItem, $purchaseOrder));
            }

            foreach ($receipt->purchaseReceiptBiaya as $fee) {
                $amount = (float) MoneyHelper::safeParse($fee->total ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $feeCurrencyId = is_numeric($fee->currency_id ?? null) ? (int) $fee->currency_id : null;
                $feeExchangeRate = $this->resolvePurchaseOrderCurrencyRate($purchaseOrder, $feeCurrencyId);
                $currencySnapshots->push([
                    'currency_id' => $feeCurrencyId,
                    'exchange_rate' => $feeExchangeRate,
                ]);

                $receiptFees[] = [
                    'receipt_id' => $receipt->id,
                    'nama_biaya' => $fee->nama_biaya,
                    'name' => $fee->nama_biaya ?: 'Biaya Lain',
                    'amount' => $amount,
                    'total' => $amount,
                    'currency_id' => $feeCurrencyId,
                    'exchange_rate' => $feeExchangeRate,
                    'amount_original' => $amount,
                    'amount_idr' => round($amount * $feeExchangeRate, 2),
                ];
            }
        }

        $currencyContext = $this->assertSingleCurrencyContext($currencySnapshots);
        $subtotal = round($subtotal, 2);
        $taxAmount = round($taxAmount, 2);
        $otherFeeTotal = round((float) collect($receiptFees)->sum(fn (array $fee) => (float) ($fee['amount'] ?? 0)), 2);

        return [
            'receipt_ids' => $receiptIds->all(),
            'purchase_order_ids' => $receipts->pluck('purchase_order_id')->filter()->unique()->values()->all(),
            'from_model_id' => $receipts->pluck('purchase_order_id')->filter()->first(),
            'cabang_id' => $receipts->pluck('cabang_id')->filter()->first(),
            'invoice_items' => $items,
            'receipt_fees' => $receiptFees,
            'currency_id' => $currencyContext['currency_id'],
            'exchange_rate' => $currencyContext['exchange_rate'],
            'subtotal' => $subtotal,
            'dpp' => $subtotal,
            'ppn_amount' => $taxAmount,
            'ppn_rate' => $subtotal > 0 ? round(($taxAmount / $subtotal) * 100, 6) : 0.0,
            'other_fee_total' => $otherFeeTotal,
            'total' => round($subtotal + $taxAmount + $otherFeeTotal, 2),
        ];
    }

    public function auditReceiptBackedInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing('invoiceItem.product', 'accountPayable');

        $expected = $this->expectedReceiptBackedInvoiceData($invoice);
        $currentItems = $invoice->invoiceItem->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product' => $item->product?->name,
            'quantity' => (float) $item->quantity,
            'price' => (float) $item->price,
            'subtotal' => (float) $item->subtotal,
            'total' => (float) $item->total,
            'tax_rate' => (float) $item->tax_rate,
            'tax_amount' => (float) $item->tax_amount,
        ])->values()->all();

        if ($expected === null) {
            return [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'receipt_backed' => false,
                'is_mismatched' => false,
                'current_items' => $currentItems,
                'expected_items' => [],
                'current' => [
                    'subtotal' => (float) $invoice->subtotal,
                    'dpp' => (float) $invoice->dpp,
                    'ppn_rate' => (float) $invoice->ppn_rate,
                    'total' => (float) $invoice->total,
                    'account_payable_total' => (float) ($invoice->accountPayable?->total_original ?? $invoice->accountPayable?->total ?? 0),
                ],
                'expected' => null,
            ];
        }

        $expectedItems = collect($expected['invoice_items'])->map(fn (array $item) => [
            'product_id' => $item['product_id'],
            'quantity' => (float) $item['quantity'],
            'price' => (float) $item['price'],
            'subtotal' => (float) $item['subtotal'],
            'total' => (float) $item['total'],
            'tax_rate' => (float) $item['tax_rate'],
            'tax_amount' => (float) $item['tax_amount'],
        ])->values()->all();

        $itemsMismatch = count($currentItems) !== count($expectedItems);
        if (! $itemsMismatch) {
            foreach ($expectedItems as $index => $expectedItem) {
                $currentItem = $currentItems[$index] ?? [];
                foreach (['product_id', 'quantity', 'price', 'subtotal', 'total', 'tax_rate', 'tax_amount'] as $key) {
                    $currentValue = $key === 'product_id' ? (int) ($currentItem[$key] ?? 0) : (float) ($currentItem[$key] ?? 0);
                    $expectedValue = $key === 'product_id' ? (int) $expectedItem[$key] : (float) $expectedItem[$key];
                    $tolerance = $key === 'product_id' ? 0 : 0.01;
                    if (abs($currentValue - $expectedValue) > $tolerance) {
                        $itemsMismatch = true;
                        break 2;
                    }
                }
            }
        }

        $currentTotal = (float) $invoice->total;
        $expectedTotal = (float) $expected['total'];

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'receipt_backed' => true,
            'is_mismatched' => $itemsMismatch
                || abs((float) $invoice->subtotal - (float) $expected['subtotal']) > 0.01
                || abs((float) $invoice->dpp - (float) $expected['dpp']) > 0.01
                || abs((float) $invoice->ppn_rate - (float) $expected['ppn_rate']) > 0.01
                || abs($currentTotal - $expectedTotal) > 0.01
                || abs((float) ($invoice->accountPayable?->total_original ?? $invoice->accountPayable?->total ?? 0) - $expectedTotal) > 0.01,
            'current_items' => $currentItems,
            'expected_items' => $expectedItems,
            'current' => [
                'subtotal' => (float) $invoice->subtotal,
                'dpp' => (float) $invoice->dpp,
                'ppn_rate' => (float) $invoice->ppn_rate,
                'ppn_amount' => (float) $invoice->ppn_amount,
                'total' => $currentTotal,
                'account_payable_total' => (float) ($invoice->accountPayable?->total_original ?? $invoice->accountPayable?->total ?? 0),
                'account_payable_remaining' => (float) ($invoice->accountPayable?->remaining_original ?? $invoice->accountPayable?->remaining ?? 0),
            ],
            'expected' => [
                'subtotal' => (float) $expected['subtotal'],
                'dpp' => (float) $expected['dpp'],
                'ppn_rate' => (float) $expected['ppn_rate'],
                'ppn_amount' => (float) $expected['ppn_amount'],
                'total' => $expectedTotal,
            ],
        ];
    }

    public function repairReceiptBackedInvoice(Invoice $invoice, bool $reverseExistingJournals = true): Invoice
    {
        return DB::transaction(function () use ($invoice, $reverseExistingJournals): Invoice {
            $invoice = Invoice::withoutGlobalScopes()
                ->with('invoiceItem', 'accountPayable')
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $expected = $this->expectedReceiptBackedInvoiceData($invoice);
            if ($expected === null) {
                throw new \RuntimeException("Invoice {$invoice->id} tidak memiliki purchase receipt yang dapat dijadikan source of truth.");
            }

            if ($reverseExistingJournals) {
                $hasOpenJournal = JournalEntry::withoutGlobalScopes()
                    ->where('source_type', Invoice::class)
                    ->where('source_id', $invoice->id)
                    ->where('is_reversal', false)
                    ->whereNull('reversal_of_transaction_id')
                    ->exists();

                if ($hasOpenJournal) {
                    app(LedgerPostingService::class)->reverseInvoiceJournalEntries($invoice);
                }
            }

            self::withoutObserverPosting(function () use ($invoice, $expected): void {
                $invoice->invoiceItem()->delete();

                foreach ($expected['invoice_items'] as $item) {
                    $invoice->invoiceItem()->create($item);
                }

                $invoice->forceFill([
                    'from_model_type' => PurchaseOrder::class,
                    'from_model_id' => $expected['from_model_id'] ?? $invoice->from_model_id,
                    'purchase_order_ids' => $expected['purchase_order_ids'],
                    'currency_id' => $expected['currency_id'] ?? $invoice->currency_id,
                    'exchange_rate' => $expected['exchange_rate'] ?? $invoice->exchange_rate ?? 1,
                    'subtotal' => $expected['subtotal'],
                    'dpp' => $expected['dpp'],
                    'tax' => $expected['ppn_rate'],
                    'ppn_rate' => $expected['ppn_rate'],
                    'other_fee' => $expected['receipt_fees'],
                    'total' => $expected['total'],
                    'cabang_id' => $expected['cabang_id'] ?? $invoice->cabang_id,
                ])->save();
            });

            return $this->finaliseInvoice($invoice->fresh(['invoiceItem', 'accountPayable']), replaceExistingJournals: false);
        });
    }

    public function normalizeInvoiceItems(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $quantity = (float) ($item['quantity'] ?? 0);
                $price = (float) MoneyHelper::safeParse($item['price'] ?? 0);
                $total = (float) MoneyHelper::safeParse($item['total'] ?? 0);

                if ($total <= 0 && $quantity > 0 && $price > 0) {
                    $total = round($quantity * $price, 2);
                }

                $item['quantity'] = $quantity;
                $item['price'] = $price;
                $item['total'] = $total;
                $item['subtotal'] = (float) MoneyHelper::safeParse($item['subtotal'] ?? $total);
                $item['tax_rate'] = (float) MoneyHelper::safeParse($item['tax_rate'] ?? 0);
                $item['tax_amount'] = (float) MoneyHelper::safeParse($item['tax_amount'] ?? 0);
                $item['discount'] = (float) MoneyHelper::safeParse($item['discount'] ?? 0);
                unset($item['price_display'], $item['total_display']);

                return $item;
            })
            ->values()
            ->all();
    }

    public function finaliseInvoice(Invoice $invoice, bool $replaceExistingJournals = true): Invoice
    {
        return DB::transaction(function () use ($invoice, $replaceExistingJournals): Invoice {
            $invoice->refresh();

            $itemSubtotal = (float) $invoice->invoiceItem()->sum('total');
            $subtotal = $itemSubtotal > 0 ? $itemSubtotal : (float) $invoice->subtotal;
            $taxSummary = $this->resolveTaxSummaryForInvoice($invoice->fresh('invoiceItem'), persistItems: true);
            $ppnRate = $taxSummary['effective_rate'];
            $ppnAmount = $taxSummary['tax_amount'];
            $otherFeeTotal = (float) $invoice->other_fee_total;
            $total = round(
                $subtotal
                + $ppnAmount
                + $otherFeeTotal
                + (float) ($invoice->pph22_amount ?? 0)
                + (float) ($invoice->bea_masuk_amount ?? 0),
                2
            );

            $cabangId = $this->resolveInvoiceCabangId($invoice) ?? $invoice->cabang_id;

            $invoice->forceFill([
                'subtotal' => $subtotal,
                'dpp' => $subtotal,
                'tax' => $ppnRate,
                'ppn_rate' => $ppnRate,
                'total' => $total,
                'cabang_id' => $cabangId,
            ])->saveQuietly();

            $this->syncAccountPayable($invoice->fresh(), $total, $cabangId);

            if ($replaceExistingJournals) {
                JournalEntry::withoutGlobalScopes()
                    ->where('source_type', Invoice::class)
                    ->where('source_id', $invoice->id)
                    ->where('is_reversal', false)
                    ->delete();
            }

            app(LedgerPostingService::class)->postInvoice($invoice->fresh(), allowRepostAfterReversal: ! $replaceExistingJournals);

            return $invoice->fresh(['invoiceItem', 'accountPayable']);
        });
    }

    public function auditInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing('invoiceItem', 'accountPayable');

        $expectedSubtotal = (float) $invoice->invoiceItem->sum('total');
        if ($expectedSubtotal <= 0) {
            $expectedSubtotal = $this->expectedSubtotalFromReceipts($invoice);
        }

        $taxSummary = $this->resolveTaxSummaryForInvoice($invoice);
        $expectedPpn = $taxSummary['tax_amount'];
        $expectedTotal = round(
            $expectedSubtotal
            + $expectedPpn
            + (float) $invoice->other_fee_total
            + (float) ($invoice->pph22_amount ?? 0)
            + (float) ($invoice->bea_masuk_amount ?? 0),
            2
        );
        $expectedCabangId = $this->resolveInvoiceCabangId($invoice) ?? $invoice->cabang_id;

        $journalEntries = JournalEntry::withoutGlobalScopes()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('is_reversal', false)
            ->whereNull('reversal_of_transaction_id')
            ->get();

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'current' => [
                'subtotal' => (float) $invoice->subtotal,
                'dpp' => (float) $invoice->dpp,
                'total' => (float) $invoice->total,
                'cabang_id' => $invoice->cabang_id,
                'account_payable_total' => (float) ($invoice->accountPayable?->total ?? 0),
                'journal_debit' => (float) $journalEntries->sum('debit'),
                'journal_credit' => (float) $journalEntries->sum('credit'),
            ],
            'expected' => [
                'subtotal' => $expectedSubtotal,
                'dpp' => $expectedSubtotal,
                'ppn_amount' => $expectedPpn,
                'ppn_rate' => $taxSummary['effective_rate'],
                'total' => $expectedTotal,
                'cabang_id' => $expectedCabangId,
            ],
            'is_mismatched' => abs((float) $invoice->subtotal - $expectedSubtotal) > 0.01
                || abs((float) $invoice->total - $expectedTotal) > 0.01
                || abs((float) ($invoice->ppn_rate ?? 0) - (float) $taxSummary['effective_rate']) > 0.01
                || (int) ($invoice->cabang_id ?? 0) !== (int) ($expectedCabangId ?? 0)
                || abs((float) ($invoice->accountPayable?->total ?? 0) - $expectedTotal) > 0.01,
        ];
    }

    protected function resolveTaxSummaryFromFormData(array $data): array
    {
        $items = $data['invoiceItem'] ?? [];
        $sourceLines = $this->sourceTaxLinesFromReceiptIds($data['purchase_receipts'] ?? $data['selected_purchase_receipts'] ?? []);

        if ($sourceLines->isEmpty()) {
            $purchaseOrderIds = collect($data['purchase_order_ids'] ?? $data['selected_purchase_orders'] ?? [])
                ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
                ->filter()
                ->values();

            if ($purchaseOrderIds->isEmpty() && ! empty($data['from_model_id']) && ($data['from_model_type'] ?? null) === PurchaseOrder::class) {
                $purchaseOrderIds = collect([(int) $data['from_model_id']]);
            }

            $sourceLines = $this->sourceTaxLinesFromPurchaseOrderIds($purchaseOrderIds);
        }

        $defaultPpnRate = $sourceLines->isEmpty()
            ? (float) MoneyHelper::safeParse($data['ppn_rate'] ?? $data['tax'] ?? 0)
            : null;

        return $this->applyTaxLinesToItems($items, $sourceLines, $defaultPpnRate, (float) ($data['subtotal'] ?? 0));
    }

    protected function resolveTaxSummaryForInvoice(Invoice $invoice, bool $persistItems = false): array
    {
        $invoice->loadMissing('invoiceItem');
        $sourceLines = $this->sourceTaxLinesFromReceiptIds($invoice->purchase_receipts ?? []);

        if ($sourceLines->isEmpty() && $invoice->from_model_type === PurchaseOrder::class && $invoice->from_model_id) {
            $sourceLines = $this->sourceTaxLinesFromPurchaseOrderIds([(int) $invoice->from_model_id]);
        }

        $defaultPpnRate = $sourceLines->isEmpty()
            ? (float) ($invoice->ppn_rate ?? $invoice->tax ?? 0)
            : null;

        $summary = $this->applyTaxLinesToItems($invoice->invoiceItem->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'price' => $item->price,
            'subtotal' => $item->subtotal,
            'total' => $item->total,
            'tax_rate' => $item->tax_rate,
            'tax_amount' => $item->tax_amount,
            'discount' => $item->discount,
        ])->all(), $sourceLines, $defaultPpnRate, (float) ($invoice->subtotal ?? 0));

        foreach ($summary['items'] as $item) {
            if ($persistItems && ! empty($item['id'])) {
                $invoice->invoiceItem()
                    ->whereKey($item['id'])
                    ->update([
                        'tax_rate' => $item['tax_rate'],
                        'tax_amount' => $item['tax_amount'],
                        'subtotal' => $item['subtotal'],
                    ]);
            }
        }

        return $summary;
    }

    protected function applyTaxLinesToItems(array $items, \Illuminate\Support\Collection $sourceLines, ?float $defaultPpnRate = null, float $fallbackSubtotal = 0): array
    {
        $remainingLines = $sourceLines->values();
        $taxAmount = 0.0;
        $subtotal = 0.0;
        $hasSourceLines = $sourceLines->isNotEmpty();

        $items = collect($items)->map(function (array $item) use (&$remainingLines, &$taxAmount, &$subtotal): array {
            $lineTotal = (float) MoneyHelper::safeParse($item['total'] ?? $item['subtotal'] ?? 0);
            $sourceIndex = $remainingLines->search(fn (array $line) => (int) ($line['product_id'] ?? 0) === (int) ($item['product_id'] ?? 0));
            $sourceLine = $sourceIndex === false ? null : $remainingLines->get($sourceIndex);

            if ($sourceLine) {
                $remainingLines->forget($sourceIndex);
                $taxRate = (float) ($sourceLine['tax_rate'] ?? 0);
                $lineTaxAmount = round($lineTotal * $taxRate / 100, 2);
            } else {
                $taxRate = (float) MoneyHelper::safeParse($item['tax_rate'] ?? 0);
                if (! $hasSourceLines && $taxRate <= 0 && $defaultPpnRate !== null) {
                    $taxRate = $defaultPpnRate;
                }
                $lineTaxAmount = (float) MoneyHelper::safeParse($item['tax_amount'] ?? 0);
                if ($lineTaxAmount <= 0 && $lineTotal > 0 && $taxRate > 0) {
                    $lineTaxAmount = round($lineTotal * $taxRate / 100, 2);
                }
            }

            $item['subtotal'] = $lineTotal;
            $item['total'] = $lineTotal;
            $item['tax_rate'] = $taxRate;
            $item['tax_amount'] = $lineTaxAmount;

            $subtotal += $lineTotal;
            $taxAmount += $lineTaxAmount;

            return $item;
        })->values()->all();

        if (empty($items) && $fallbackSubtotal > 0 && $defaultPpnRate !== null) {
            $subtotal = $fallbackSubtotal;
            $taxAmount = round($fallbackSubtotal * $defaultPpnRate / 100, 2);
        }

        return [
            'items' => $items,
            'tax_amount' => round($taxAmount, 2),
            'effective_rate' => $subtotal > 0 ? round(($taxAmount / $subtotal) * 100, 6) : 0.0,
        ];
    }

    protected function sourceTaxLinesFromReceiptIds($receiptIds): \Illuminate\Support\Collection
    {
        $receiptIds = collect($receiptIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($receiptIds->isEmpty()) {
            return collect();
        }

        return PurchaseReceipt::withoutGlobalScopes()
            ->with('purchaseReceiptItem.purchaseOrderItem')
            ->whereIn('id', $receiptIds)
            ->get()
            ->flatMap(fn (PurchaseReceipt $receipt) => $receipt->purchaseReceiptItem->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) ($item->qty_accepted ?? 0),
                'tax_rate' => (float) ($item->purchaseOrderItem?->tax ?? 0),
                'tipe_pajak' => $item->purchaseOrderItem?->tipe_pajak,
            ]))
            ->values();
    }

    protected function resolvePurchaseOrderItemForReceiptItem($receiptItem, ?PurchaseOrder $purchaseOrder): ?\App\Models\PurchaseOrderItem
    {
        if ($receiptItem?->purchaseOrderItem?->exists) {
            return $receiptItem->purchaseOrderItem;
        }

        if ($purchaseOrder && is_numeric($receiptItem?->purchase_order_item_id ?? null)) {
            $item = $purchaseOrder->purchaseOrderItem->firstWhere('id', (int) $receiptItem->purchase_order_item_id);
            if ($item) {
                return $item;
            }
        }

        return $purchaseOrder?->purchaseOrderItem?->firstWhere('product_id', $receiptItem?->product_id);
    }

    protected function buildReceiptBackedInvoiceItem($receiptItem, \App\Models\PurchaseOrderItem $purchaseOrderItem, float $quantity): array
    {
        $unitPrice = (float) ($purchaseOrderItem->unit_price ?? 0);
        $discountPct = (float) ($purchaseOrderItem->discount ?? 0);
        $afterDiscount = $unitPrice * (1 - ($discountPct / 100));

        $taxRate = (float) ($purchaseOrderItem->tax ?? 0);
        $taxType = strtolower(trim((string) ($purchaseOrderItem->tipe_pajak ?? 'eklusif')));
        $isInclusive = in_array($taxType, ['inklusif', 'inclusive'], true);

        $dppUnitPrice = $afterDiscount;
        if ($isInclusive && $taxRate > 0) {
            $dppUnitPrice = $afterDiscount / (1 + ($taxRate / 100));
        }

        $lineTotal = round($dppUnitPrice * $quantity, 2);
        $lineTaxAmount = round($lineTotal * ($taxRate / 100), 2);

        return [
            'product_id' => $receiptItem->product_id,
            'quantity' => $quantity,
            'price' => round($dppUnitPrice, 10),
            'discount' => 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $lineTaxAmount,
            'subtotal' => $lineTotal,
            'total' => $lineTotal,
        ];
    }

    protected function resolvePurchaseOrderItemCurrencySnapshot(\App\Models\PurchaseOrderItem $item, ?PurchaseOrder $purchaseOrder): array
    {
        $currencyId = is_numeric($item->currency_id ?? null) ? (int) $item->currency_id : null;

        return [
            'currency_id' => $currencyId,
            'exchange_rate' => $this->resolvePurchaseOrderCurrencyRate($purchaseOrder, $currencyId),
        ];
    }

    protected function resolvePurchaseOrderCurrencyRate(?PurchaseOrder $purchaseOrder, ?int $currencyId): float
    {
        if (! $currencyId) {
            return 1.0;
        }

        $purchaseOrder?->loadMissing('purchaseOrderCurrency');
        $poCurrency = $purchaseOrder?->purchaseOrderCurrency?->firstWhere('currency_id', $currencyId);
        $rate = (float) ($poCurrency?->nominal ?? CurrencyConversionResolver::resolveRate($currencyId));

        return $rate > 0 ? $rate : 1.0;
    }

    protected function assertSingleCurrencyContext(\Illuminate\Support\Collection $snapshots): array
    {
        $normalized = $snapshots
            ->map(function (array $snapshot): array {
                $currencyId = is_numeric($snapshot['currency_id'] ?? null) ? (int) $snapshot['currency_id'] : null;
                $exchangeRate = (float) ($snapshot['exchange_rate'] ?? CurrencyConversionResolver::resolveRate($currencyId));

                return [
                    'currency_id' => $currencyId,
                    'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.0,
                ];
            })
            ->unique(fn (array $snapshot) => ($snapshot['currency_id'] ?? 'null') . ':' . number_format((float) $snapshot['exchange_rate'], 8, '.', ''))
            ->values();

        if ($normalized->count() > 1) {
            throw ValidationException::withMessages([
                'currency_id' => 'Purchase invoice hanya boleh berisi satu mata uang dan satu rate. Pisahkan receipt/PO multi-currency ke invoice berbeda.',
            ]);
        }

        return $normalized->first() ?? [
            'currency_id' => CurrencyConversionResolver::resolveCurrencyIdByCode('IDR'),
            'exchange_rate' => 1.0,
        ];
    }

    public function currencyContextFromPurchaseOrderIds($purchaseOrderIds): ?array
    {
        $purchaseOrderIds = collect($purchaseOrderIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($purchaseOrderIds->isEmpty()) {
            return null;
        }

        $purchaseOrders = PurchaseOrder::withoutGlobalScopes()
            ->with(['purchaseOrderItem', 'purchaseOrderCurrency'])
            ->whereIn('id', $purchaseOrderIds)
            ->get();

        if ($purchaseOrders->isEmpty()) {
            return null;
        }

        $snapshots = $purchaseOrders->flatMap(function (PurchaseOrder $purchaseOrder) {
            return $purchaseOrder->purchaseOrderItem->map(
                fn ($item) => $this->resolvePurchaseOrderItemCurrencySnapshot($item, $purchaseOrder)
            );
        });

        return $this->assertSingleCurrencyContext($snapshots);
    }

    protected function sourceTaxLinesFromPurchaseOrderIds($purchaseOrderIds): \Illuminate\Support\Collection
    {
        $purchaseOrderIds = collect($purchaseOrderIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($purchaseOrderIds->isEmpty()) {
            return collect();
        }

        return PurchaseOrder::withoutGlobalScopes()
            ->with('purchaseOrderItem')
            ->whereIn('id', $purchaseOrderIds)
            ->get()
            ->flatMap(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->purchaseOrderItem->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) ($item->quantity ?? 0),
                'tax_rate' => (float) ($item->tax ?? 0),
                'tipe_pajak' => $item->tipe_pajak,
            ]))
            ->values();
    }

    public function repairInvoice(Invoice $invoice, bool $reverseExistingJournals = true): Invoice
    {
        return DB::transaction(function () use ($invoice, $reverseExistingJournals): Invoice {
            if ($reverseExistingJournals) {
                $hasJournal = JournalEntry::withoutGlobalScopes()
                    ->where('source_type', Invoice::class)
                    ->where('source_id', $invoice->id)
                    ->where('is_reversal', false)
                    ->exists();

                if ($hasJournal) {
                    app(LedgerPostingService::class)->reverseInvoiceJournalEntries($invoice);
                }
            }

            return $this->finaliseInvoice($invoice, replaceExistingJournals: false);
        });
    }

    protected function resolveSubtotal(array $data): float
    {
        $itemSubtotal = (float) collect($data['invoiceItem'] ?? [])
            ->sum(fn (array $item) => (float) MoneyHelper::safeParse($item['total'] ?? 0));

        if ($itemSubtotal > 0) {
            return round($itemSubtotal, 2);
        }

        return round((float) MoneyHelper::safeParse($data['subtotal'] ?? $data['dpp'] ?? 0), 2);
    }

    protected function normalizeOtherFees(array $data): array
    {
        $otherFees = [];
        if (isset($data['other_fees']) && is_array($data['other_fees'])) {
            $otherFees = array_merge($otherFees, $data['other_fees']);
        }
        if (isset($data['receiptBiayaItems']) && is_array($data['receiptBiayaItems'])) {
            $otherFees = array_merge($otherFees, $data['receiptBiayaItems']);
        }

        return collect($otherFees)
            ->map(function (array $fee): ?array {
                $amount = (float) MoneyHelper::safeParse($fee['total'] ?? $fee['amount'] ?? 0);

                if ($amount <= 0) {
                    return null;
                }

                return [
                    'name' => $fee['nama_biaya'] ?? $fee['name'] ?? 'Biaya Lain',
                    'amount' => $amount,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function syncAccountPayable(Invoice $invoice, float $total, ?int $cabangId): void
    {
        if ($invoice->from_model_type !== PurchaseOrder::class) {
            return;
        }

        $supplierId = $invoice->fromModel?->supplier_id;
        if (! $supplierId) {
            return;
        }

        $accountPayable = AccountPayable::firstOrNew(['invoice_id' => $invoice->id]);
        $currencyId = is_numeric($invoice->currency_id ?? null) ? (int) $invoice->currency_id : CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');
        $exchangeRate = (float) ($invoice->exchange_rate ?? CurrencyConversionResolver::resolveRate($currencyId));
        $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;

        $totalOriginal = round($total, 4);
        $paidOriginal = $accountPayable->exists
            ? (float) ($accountPayable->paid_original ?? (($accountPayable->paid ?? 0) / $exchangeRate))
            : 0.0;
        $remainingOriginal = max(0, $totalOriginal - $paidOriginal);

        $totalIdr = round($totalOriginal * $exchangeRate, 2);
        $paidIdr = round($paidOriginal * $exchangeRate, 2);
        $remainingIdr = max(0, round($remainingOriginal * $exchangeRate, 2));

        $accountPayable->fill([
            'supplier_id' => $supplierId,
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate,
            'total_original' => $totalOriginal,
            'paid_original' => $paidOriginal,
            'remaining_original' => $remainingOriginal,
            'total' => $totalIdr,
            'paid' => $paidIdr,
            'remaining' => $remainingIdr,
            'status' => $remainingOriginal <= 0.01 ? PaymentStatus::PAID->value : PaymentStatus::UNPAID->value,
            'cabang_id' => $cabangId,
        ]);
        $accountPayable->save();

        if (! $accountPayable->ageingSchedule()->exists()) {
            try {
                $daysOutstanding = ($invoice->invoice_date && $invoice->due_date)
                    ? Carbon::parse($invoice->invoice_date)->diffInDays(Carbon::parse($invoice->due_date))
                    : 0;
            } catch (\Throwable) {
                $daysOutstanding = 0;
            }

            $accountPayable->ageingSchedule()->create([
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'days_outstanding' => $daysOutstanding,
                'bucket' => 'Current',
            ]);
        }
    }

    protected function resolveSourceCabangId(array $data): ?int
    {
        $receiptIds = collect($data['purchase_receipts'] ?? $data['selected_purchase_receipts'] ?? [])
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($receiptIds->isNotEmpty()) {
            $receiptCabangId = PurchaseReceipt::withoutGlobalScopes()
                ->whereIn('id', $receiptIds)
                ->whereNotNull('cabang_id')
                ->value('cabang_id');

            if ($receiptCabangId) {
                return (int) $receiptCabangId;
            }
        }

        $purchaseOrderIds = collect($data['purchase_order_ids'] ?? $data['selected_purchase_orders'] ?? [])
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($purchaseOrderIds->isEmpty() && ! empty($data['from_model_id']) && ($data['from_model_type'] ?? null) === PurchaseOrder::class) {
            $purchaseOrderIds = collect([(int) $data['from_model_id']]);
        }

        if ($purchaseOrderIds->isNotEmpty()) {
            $purchaseOrder = PurchaseOrder::withoutGlobalScopes()->find($purchaseOrderIds->first());
            return $purchaseOrder ? $this->resolvePurchaseOrderCabangId($purchaseOrder) : null;
        }

        return null;
    }

    protected function resolveInvoiceCabangId(Invoice $invoice): ?int
    {
        $receiptIds = collect($invoice->purchase_receipts ?? [])
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($receiptIds->isNotEmpty()) {
            $receiptCabangId = PurchaseReceipt::withoutGlobalScopes()
                ->whereIn('id', $receiptIds)
                ->whereNotNull('cabang_id')
                ->value('cabang_id');

            if ($receiptCabangId) {
                return (int) $receiptCabangId;
            }
        }

        if ($invoice->from_model_type === PurchaseOrder::class && $invoice->from_model_id) {
            $purchaseOrder = PurchaseOrder::withoutGlobalScopes()->find($invoice->from_model_id);
            return $purchaseOrder ? $this->resolvePurchaseOrderCabangId($purchaseOrder) : null;
        }

        return null;
    }

    protected function resolvePurchaseOrderCabangId(PurchaseOrder $purchaseOrder): ?int
    {
        if ($purchaseOrder->cabang_id) {
            return (int) $purchaseOrder->cabang_id;
        }

        $purchaseOrder->loadMissing('purchaseOrderItem.referItemModel');
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            if ($item->referItemModel?->cabang_id) {
                return (int) $item->referItemModel->cabang_id;
            }
        }

        if ($purchaseOrder->refer_model_type && $purchaseOrder->refer_model_id && class_exists($purchaseOrder->refer_model_type)) {
            $referModel = $purchaseOrder->refer_model_type::withoutGlobalScopes()->find($purchaseOrder->refer_model_id);
            if ($referModel?->cabang_id) {
                return (int) $referModel->cabang_id;
            }
        }

        return null;
    }

    protected function expectedSubtotalFromReceipts(Invoice $invoice): float
    {
        $receiptIds = collect($invoice->purchase_receipts ?? [])
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($receiptIds->isEmpty()) {
            return 0.0;
        }

        $receipts = PurchaseReceipt::withoutGlobalScopes()
            ->with('purchaseReceiptItem.purchaseOrderItem')
            ->whereIn('id', $receiptIds)
            ->get();

        return (float) $receipts->sum(function (PurchaseReceipt $receipt): float {
            return (float) $receipt->purchaseReceiptItem->sum(function ($item): float {
                return (float) ($item->qty_accepted ?? 0) * (float) ($item->purchaseOrderItem?->unit_price ?? 0);
            });
        });
    }
}
