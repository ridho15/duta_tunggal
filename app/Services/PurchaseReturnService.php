<?php

namespace App\Services;

use App\Models\PurchaseReturn;

class PurchaseReturnService
{
    public function generateNotaRetur()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $lastPurchaseReturn = PurchaseReturn::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastPurchaseReturn) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastPurchaseReturn->nota_retur, -4));
            $number = $lastNumber + 1;
        }

        return 'NR-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
