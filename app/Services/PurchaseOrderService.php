<?php

namespace App\Services;

use App\Models\PurchaseOrder;

class PurchaseOrderService
{
    public function updateTotalAmount($purchaseOrder)
    {
        $total = 0;
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            $unit_price = $item->unit_price * $item->currency->to_rupiah;
            $discount = $item->discount * $item->currency->to_rupiah;
            $tax = $item->tax = $item->currency->to_rupiah;
            $total += ($item->quantity * $unit_price) - $discount + $tax;
        }

        foreach ($purchaseOrder->purchaseOrderBiaya as $item) {
            $total += $item->total * $item->currency->to_rupiah;
        }

        $purchaseOrder->update([
            'total_amount' => $total
        ]);

        return $purchaseOrder;
    }

    public function createPoFromSo($saleOrder) {}

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

        // Hitung berapa PO pada hari ini
        $lastPo = PurchaseOrder::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastPo) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastPo->po_number, -4));
            $number = $lastNumber + 1;
        }

        return 'PO-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
