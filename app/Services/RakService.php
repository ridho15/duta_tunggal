<?php

namespace App\Services;

use App\Models\Rak;

class RakService
{
    public function generateKodeRak($warehouseId = null)
    {
        $prefix = 'RAK-';

        // Get the last rak code
        $query = Rak::where('code', 'like', $prefix . '%');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $last = $query->orderBy('id', 'desc')->first();

        $number = 1;

        if ($last) {
            // Extract the number from the last code
            $lastCode = str_replace($prefix, '', $last->code);
            $number = intval($lastCode) + 1;
        }

        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}