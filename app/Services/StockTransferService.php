<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\StockTransfer;
use Carbon\Carbon;

class StockTransferService
{
    public function generateTransferNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = StockTransfer::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->transfer_number, -4));
            $number = $lastNumber + 1;
        }

        return 'TN-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function requestTransfer($stockTransfer)
    {
        return $stockTransfer->update([
            'status' => 'Request'
        ]);
    }

    public function approveStockTransfer($stockTransfer)
    {
        foreach ($stockTransfer->stockTransferItem as $stockTransferItem) {
            // transfer out
            $stockTransferItem->stockMovement()->create([
                'product_id' => $stockTransferItem->product_id,
                'warehouse_id' => $stockTransferItem->from_warehouse_id,
                'quantity' => $stockTransferItem->quantity,
                'type' => 'transfer_out',
                'date' => Carbon::now(),
                'rak_id' => $stockTransferItem->from_rak_id,
            ]);
            // transfer in
            $stockTransferItem->stockMovement()->create([
                'product_id' => $stockTransferItem->product_id,
                'warehouse_id' => $stockTransferItem->to_warehouse_id,
                'quantity' => $stockTransferItem->quantity,
                'type' => 'transfer_in',
                'date' => Carbon::now(),
                'rak_id' => $stockTransferItem->to_rak_id,
            ]);
        }
        $stockTransfer->update([
            'status' => 'Approved'
        ]);
        return $stockTransfer;
    }
}
