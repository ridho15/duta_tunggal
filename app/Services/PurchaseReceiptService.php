<?php

namespace App\Services;

use App\Models\PurchaseReceipt;

class PurchaseReceiptService
{
    public function generateReceiptNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $lastPurchaseReceipt = PurchaseReceipt::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastPurchaseReceipt) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastPurchaseReceipt->receipt_number, -4));
            $number = $lastNumber + 1;
        }

        return 'RN-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
