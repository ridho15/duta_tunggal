<?php

namespace App\Services;

use App\Models\Rak;

class RakService
{
    public function generateKodeRak($warehouseId = null)
    {
        $date = now()->format('Ymd');
        $prefix = 'RAK-';
        if ($warehouseId) {
            $prefix .= $warehouseId . '-'; // optionally include warehouse part for uniqueness
        }
        $prefix .= $date . '-';

        do {
            $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT); // three digits remains
            $candidate = $prefix . $random;
            $existsQuery = Rak::where('code', $candidate);
            if ($warehouseId) {
                $existsQuery->where('warehouse_id', $warehouseId);
            }
            $exists = $existsQuery->exists();
        } while ($exists);

        return $candidate;
    }
}