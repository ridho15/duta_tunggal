<?php

namespace App\Services;

use App\Models\BillOfMaterial;

class BillOfMaterialService
{
    public function generateCode()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = BillOfMaterial::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->code, -4));
            $number = $lastNumber + 1;
        }

        return 'BOM-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
