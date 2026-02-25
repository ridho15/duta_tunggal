<?php

namespace App\Services;

use App\Models\Warehouse;

class WarehouseService
{
    public function generateKodeGudang()
    {
        $date = now()->format('Ymd');
        $prefix = 'GD-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = Warehouse::where('kode', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
