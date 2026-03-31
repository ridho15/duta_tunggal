<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
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

        $total = 0;

        // Hitung total dari purchase order items
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            $total += HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax, $item->tipe_pajak);
        }

        // Hitung total dari biaya lain (purchase order biaya)
        foreach ($purchaseOrder->purchaseOrderBiaya as $biaya) {
            // Konversi ke Rupiah jika mata uang berbeda
            $biayaAmount = $biaya->total * ($biaya->currency->to_rupiah ?? 1);
            $total += $biayaAmount;
        }

        $purchaseOrder->update([
            'total_amount' => $total
        ]);

        self::$updatingTotalAmount = false;

        return $purchaseOrder;
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
     * Build invoice other fees from PO biaya lines.
     * Falls back to a single manual line when no PO biaya is marked for invoice.
     */
    protected function buildInvoiceOtherFees(PurchaseOrder $purchaseOrder, mixed $fallbackOtherFee = null): array
    {
        $purchaseOrder->loadMissing('purchaseOrderBiaya.currency');

        $otherFees = [];

        foreach ($purchaseOrder->purchaseOrderBiaya as $biaya) {
            if ((int) ($biaya->masuk_invoice ?? 0) !== 1) {
                continue;
            }

            $conversionRate = (float) ($biaya->currency->to_rupiah ?? 1);
            $otherFees[] = [
                'name' => $biaya->nama_biaya ?? 'Biaya Lain',
                'amount' => round(((float) ($biaya->total ?? 0)) * $conversionRate, 2),
            ];
        }

        if (!empty($otherFees)) {
            return $otherFees;
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
