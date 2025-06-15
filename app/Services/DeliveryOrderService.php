<?php

namespace App\Services;

use App\Models\DeliveryOrderLog;
use Illuminate\Support\Facades\Auth;

class DeliveryOrderService
{
    public function updateStatus($deliveryOrder, $status)
    {
        $deliveryOrder->update([
            'status' => $status
        ]);

        $this->createLog(delivery_order_id: $deliveryOrder->id, status: $status);
    }

    public function createLog($delivery_order_id, $status)
    {
        DeliveryOrderLog::create([
            'delivery_order_id' => $delivery_order_id,
            'status' => $status,
            'confirmed_by' => Auth::user()->id,
        ]);
    }

    public function updateQuantity(){

    }
}
