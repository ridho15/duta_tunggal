<?php

namespace App\Services;

use App\Models\Production;

class ProductionService
{
    public function generateProductionNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'PRO-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = Production::where('production_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
