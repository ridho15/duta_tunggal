<?php

namespace App\Services;

use App\Models\BillOfMaterial;

class BillOfMaterialService
{
    public function generateCode()
    {
        $date = now()->format('Ymd');
        $prefix = 'BOM-' . $date . '-';

        // pick random suffix and avoid duplicates
        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = BillOfMaterial::where('code', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
