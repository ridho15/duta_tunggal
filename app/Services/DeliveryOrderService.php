<?php

namespace App\Services;

use App\Models\DeliveryOrder;
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

    public function updateQuantity() {}

    public function generateDoNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = DeliveryOrder::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->do_number, -4));
            $number = $lastNumber + 1;
        }

        return 'DO-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
