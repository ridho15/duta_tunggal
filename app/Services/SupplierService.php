<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\Rak;

class SupplierService
{
    /**
     * @deprecated Use \App\Services\RakService::generateKodeRak() instead.
     */
    public function generateKodeRak($warehouseId = null)
    {
        return app(RakService::class)->generateKodeRak($warehouseId);
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
