<?php

namespace App\Services;

use App\Models\Warehouse;

class WarehouseService
{
    public function generateKodeGudang()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = Warehouse::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->kode, -4));
            $number = $lastNumber + 1;
        }

        return 'GD-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
