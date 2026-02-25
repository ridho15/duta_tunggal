<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\Rak;

class SupplierService
{
    public function generateKodeRak($warehouseId = null)
    {
        $prefix = 'RAK-';

        // build query optionally filtering by warehouse
        $query = Rak::where('code', 'like', $prefix . '%');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        do {
            $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $existsQuery = $query->where('code', $candidate);
            $exists = $existsQuery->exists();
        } while ($exists);

        return $candidate;
    }

    public function generateCode()
    {
        $date = now()->format('Ymd');
        $prefix = 'SP-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = Supplier::where('code', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
