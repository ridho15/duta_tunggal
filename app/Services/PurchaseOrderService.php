<?php

namespace App\Services;

class PurchaseOrderService
{
    public function setTotalAmount($purchaseOrder)
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
}
