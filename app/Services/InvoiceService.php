<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
    public function generateInvoiceNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $lastInvoice = Invoice::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastInvoice) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $number = $lastNumber + 1;
        }

        return 'INV-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
