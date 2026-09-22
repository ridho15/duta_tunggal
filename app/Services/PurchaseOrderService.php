<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Support\CurrencyConversionResolver;
use App\Support\OrderRequestQuantityLock;
use App\Helpers\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderService
{
    private static $updatingTotalAmount = false;

    public static function isUpdatingTotalAmount()
    {
        return self::$updatingTotalAmount;
    }

    public function updateTotalAmount($purchaseOrder)
    {
        // Prevent infinite loop when called from observer
        if (self::$updatingTotalAmount) {
            return $purchaseOrder;
        }

        self::$updatingTotalAmount = true;

        try {
            $total = $this->calculateTotalAmount($purchaseOrder);

            $purchaseOrder->update([
                'total_amount' => number_format((float) $total, 2, '.', '')
            ]);
        } finally {
            self::$updatingTotalAmount = false;
        }

        return $purchaseOrder;
    }

    public function calculateTotalAmount(PurchaseOrder $purchaseOrder): float
    {
        $summary = $this->calculateTotalSummary($purchaseOrder);

        return (float) $summary['total_idr'];
    }

    public function calculateTotalSummary(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(['purchaseOrderCurrency.currency', 'purchaseOrderItem.currency']);
        $poCurrencies = $purchaseOrder->purchaseOrderCurrency->keyBy('currency_id');

        $totalIdr = 0.0;
        $items = [];
        $currencyTotals = [];

        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            $currencyId = is_numeric($item->currency_id ?? null) ? (int) $item->currency_id : null;
            $subtotal = (float) HelperController::hitungSubtotal(
                (float) ($item->quantity ?? 0),
                (float) ($item->unit_price ?? 0),
                (float) ($item->discount ?? 0),
                (float) ($item->tax ?? 0),
                $item->tipe_pajak
            );

            $rate = $this->resolvePurchaseOrderItemRate($poCurrencies, $currencyId);
            $subtotalIdr = (float) bcmul((string) MoneyHelper::parseHighPrecision($subtotal), (string) $rate, 10);
            $totalIdr += $subtotalIdr;

            $currencyCode = $item->currency?->code ?: 'IDR';
            $currencySymbol = $item->currency?->symbol ?: 'Rp';

            if (! isset($currencyTotals[$currencyCode])) {
                $currencyTotals[$currencyCode] = [
                    'currency_id' => $currencyId,
                    'currency_code' => $currencyCode,
                    'currency_symbol' => $currencySymbol,
                    'subtotal' => 0.0,
                    'subtotal_idr' => 0.0,
                    'rate' => $rate,
                ];
            }

            $currencyTotals[$currencyCode]['subtotal'] += $subtotal;
            $currencyTotals[$currencyCode]['subtotal_idr'] += $subtotalIdr;

            $items[] = [
                'item_id' => $item->id,
                'currency_id' => $currencyId,
                'currency_code' => $currencyCode,
                'currency_symbol' => $currencySymbol,
                'subtotal' => $subtotal,
                'rate' => $rate,
                'subtotal_idr' => $subtotalIdr,
            ];
        }

        return [
            'total_idr' => round($totalIdr, 2),
            'items' => $items,
            'currency_totals' => array_values($currencyTotals),
        ];
    }

    protected function resolvePurchaseOrderItemRate($poCurrencies, ?int $currencyId): float
    {
        if (! $currencyId) {
            return 1.0;
        }

        $poCurrency = $poCurrencies->get($currencyId);
        if ($poCurrency && (float) ($poCurrency->nominal ?? 0) > 0) {
            return (float) $poCurrency->nominal;
        }

        return CurrencyConversionResolver::resolveRate($currencyId);
    }

    /**
     * Create a Purchase Order from a Sale Order (drop-ship scenario).
     *
     * NOTE: The full implementation lives in SalesOrderService::createPurchaseOrder().
     * Call that method from the SO side, which has access to the full form data
     * (supplier_id, warehouse_id, expected_date, tempo_hutang, po_number, note).
     *
     * @deprecated Use SalesOrderService::createPurchaseOrder($saleOrder, $data) instead.
     * @throws \BadMethodCallException
     */
    public function createPoFromSo($saleOrder): never
    {
        throw new \BadMethodCallException(
            'PurchaseOrderService::createPoFromSo is deprecated. Use SalesOrderService::createPurchaseOrder($saleOrder, $data) instead.'
        );
    }

    /**
     * Approve a Purchase Order.
     * Sets status=approved, date_approved, and approved_by.
     * Fulfillment quantities are maintained when PurchaseOrderItems are created
     * or deleted. Approval only finalizes the PO status and syncs the parent
     * Order Request status from the already-updated item quantities.
     */
    public function approvePo(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        OrderRequestQuantityLock::validatePurchaseOrderApproval($purchaseOrder);

        $purchaseOrder->update([
            'status'        => 'approved',
            'date_approved' => Carbon::now(),
            'approved_by'   => $userId ?? Auth::id(),
        ]);

        // Re-evaluate the linked Order Request status using the current fulfillment totals.
        if ($purchaseOrder->refer_model_type === 'App\\Models\\OrderRequest') {
            $orderRequest = \App\Models\OrderRequest::find($purchaseOrder->refer_model_id);
            if ($orderRequest) {
                $orderRequest->syncFulfillmentStatus();
            }
        }

        return $purchaseOrder;
    }

    public function generateInvoice($purchaseOrder, $data)
    {
        $subtotal = 0;
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            $subtotal += $item->quantity * $item->unit_price - $item->discount + $item->tax;
        }

        $otherFees = $this->buildInvoiceOtherFees($purchaseOrder, $data['other_fee'] ?? null);
        $otherFeeTotal = (float) collect($otherFees)->sum(fn (array $fee) => (float) ($fee['amount'] ?? 0));
        $total = $subtotal + $data['tax'] + $otherFeeTotal;
        $invoice = $purchaseOrder->invoice()->create([
            'invoice_number' => $data['invoice_number'],
            'invoice_date' => $data['invoice_date'],
            'tax' => $data['tax'],
            'other_fee' => $otherFees,
            'due_date' => $data['due_date'],
            'status' => 'draft',
            'subtotal' => $subtotal,
            'total' => $total
        ]);

        foreach ($purchaseOrder->purchaseOrderItem as $purchaseOrderItem) {
            $price = $purchaseOrderItem->unit_price + $purchaseOrderItem->tax - $purchaseOrderItem->discount;
            $total = $price * $purchaseOrderItem->quantity;
            $invoice->invoiceItem()->create([
                'product_id' => $purchaseOrderItem->product_id,
                'quantity' => $purchaseOrderItem->quantity,
                'price' => $price,
                'total' => $total
            ]);
        }

        return true;
    }

    /**
     * Build invoice other fees from an explicit action value only.
     * Purchase-side biaya lain now belongs on Purchase Invoice, not Purchase Order.
     */
    protected function buildInvoiceOtherFees(PurchaseOrder $purchaseOrder, mixed $fallbackOtherFee = null): array
    {
        $purchaseOrder->loadMissing('purchaseOrderBiaya.currency');

        $receiptFees = $purchaseOrder->purchaseOrderBiaya
            ->filter(fn ($fee) => (int) ($fee->masuk_invoice ?? 0) === 1)
            ->map(function ($fee) {
                return [
                    'name' => $fee->nama_biaya ?: 'Biaya Lain',
                    'amount' => round((float) ($fee->total ?? 0), 2),
                ];
            })
            ->values()
            ->all();

        if (! empty($receiptFees)) {
            return $receiptFees;
        }

        $fallbackAmount = (float) ($fallbackOtherFee ?? 0);
        if ($fallbackAmount <= 0) {
            return [];
        }

        return [[
            'name' => 'Biaya Lain',
            'amount' => round($fallbackAmount, 2),
        ]];
    }

    public function generatePoNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'PO-' . $date . '-';

        // Use sequential numbering (consistent with SO/INV/QO generators)
        $max = PurchaseOrder::withoutGlobalScopes()
            ->where('po_number', 'like', $prefix . '%')
            ->max('po_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        // Guard against concurrent inserts
        do {
            $candidate = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            $exists = PurchaseOrder::withoutGlobalScopes()
                ->where('po_number', $candidate)
                ->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $candidate;
    }
}
