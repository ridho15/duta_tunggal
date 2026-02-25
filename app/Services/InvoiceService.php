<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
    public function generateInvoiceNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'INV-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = Invoice::where('invoice_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
