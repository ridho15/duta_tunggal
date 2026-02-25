<?php

namespace App\Services;

use App\Models\Cabang;

class CabangService
{
    public function generateKodeCabang()
    {
        $date = now()->format('Ymd');
        $prefix = 'CB-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = Cabang::where('kode', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
