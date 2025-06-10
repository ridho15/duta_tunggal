<?php

namespace App\Services;

use App\Http\Controllers\HelperController;

class SalesOrderService
{
    public function updateTotalAmount($salesOrder)
    {
        $total_amount = 0;
        foreach ($salesOrder->saleOrderItem as $item) {
            $total_amount += HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax);
        }

        $salesOrder->update([
            'total_amount' => $total_amount
        ]);
    }
}
