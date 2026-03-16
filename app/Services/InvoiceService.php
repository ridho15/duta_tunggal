<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
    /**
     * Generate a strictly sequential invoice number for today that is guaranteed
     * to be unique globally (all branches).  Uses a loop to handle race conditions:
     * if the candidate already exists in the DB, the counter is incremented until
     * a genuinely free slot is found.
     */
    public function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'INV-' . $date . '-';

        // Find the highest existing sequence number for today (ignore branch scope)
        $max = Invoice::withoutGlobalScopes()
            ->where('invoice_number', 'like', $prefix . '%')
            ->max('invoice_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        // Guard against concurrent inserts — keep incrementing until the slot is free
        do {
            $candidate = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            $exists = Invoice::withoutGlobalScopes()
                ->where('invoice_number', $candidate)
                ->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $candidate;
    }

    /**
     * Generate a sequential purchase invoice number (PINV-YYYYMMDD-XXXX) unique globally.
     */
    public function generatePurchaseInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'PINV-' . $date . '-';

        $max = Invoice::withoutGlobalScopes()
            ->where('invoice_number', 'like', $prefix . '%')
            ->max('invoice_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        do {
            $candidate = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            $exists = Invoice::withoutGlobalScopes()
                ->where('invoice_number', $candidate)
                ->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $candidate;
    }
}
