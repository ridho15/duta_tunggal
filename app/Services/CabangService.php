<?php

namespace App\Services;

use App\Models\Cabang;

class CabangService
{
    public function generateKodeCabang()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = Cabang::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->kode, -4));
            $number = $lastNumber + 1;
        }

        return 'CB-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
