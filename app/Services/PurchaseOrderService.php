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
     */
    public function createPoFromSo($saleOrder) {}

    /**
     * Approve a Purchase Order.
     * Sets status=approved, date_approved, and approved_by.
     * If the PO was created from an OrderRequest, updates fulfilled_quantity
     * on the linked OrderRequestItems based on the current PO item quantities.
     */
    public function approvePo(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        $purchaseOrder->update([
            'status'        => 'approved',
            'date_approved' => Carbon::now(),
            'approved_by'   => $userId ?? Auth::id(),
        ]);

        // Update fulfilled_quantity on linked OrderRequest items using current PO quantities
        if ($purchaseOrder->refer_model_type === 'App\\Models\\OrderRequest') {
            $purchaseOrder->loadMissing('purchaseOrderItem');
            foreach ($purchaseOrder->purchaseOrderItem as $poItem) {
                if (
                    $poItem->refer_item_model_type === 'App\\Models\\OrderRequestItem'
                    && $poItem->refer_item_model_id
                ) {
                    $orItem = \App\Models\OrderRequestItem::find($poItem->refer_item_model_id);
                    if ($orItem) {
                        $orItem->addFulfilledQuantity($poItem->quantity);
                    }
                }
            }

            // Update the OrderRequest status to partial or complete
            $orderRequest = \App\Models\OrderRequest::find($purchaseOrder->refer_model_id);
            if ($orderRequest) {
                $orderRequest->loadMissing('orderRequestItem');
                $allFulfilled = $orderRequest->orderRequestItem->every(function ($item) {
                    return ($item->fulfilled_quantity ?? 0) >= $item->quantity;
                });
                $anyFulfilled = $orderRequest->orderRequestItem->contains(function ($item) {
                    return ($item->fulfilled_quantity ?? 0) > 0;
                });
                if ($allFulfilled) {
                    $orderRequest->update(['status' => 'complete']);
                } elseif ($anyFulfilled) {
                    $orderRequest->update(['status' => 'partial']);
                }
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

        $total = $subtotal + $data['tax'] + $data['other_fee'];
        $invoice = $purchaseOrder->invoice()->create([
            'invoice_number' => $data['invoice_number'],
            'invoice_date' => $data['invoice_date'],
            'tax' => $data['tax'],
            'other_fee' => $data['other_fee'],
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
