<?php

namespace App\Services;

use App\Models\PurchaseOrder;

class PurchaseOrderService
{
    public function updateTotalAmount($purchaseOrder)
    {
        $total = 0;
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            $total += $item->quantity * $item->unit_price - $item->discount + $item->tax;
        }

        $purchaseOrder->update([
            'total_amount' => $total
        ]);

        return $purchaseOrder;
    }

    public function createPoFromSo($saleOrder) {}

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
