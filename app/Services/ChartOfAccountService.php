<?php

namespace App\Services;

use App\Models\ChartOfAccount;

class ChartOfAccountService
{
    public function generateCode()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = ChartOfAccount::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->code, -4));
            $number = $lastNumber + 1;
        }

        return 'COA-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
