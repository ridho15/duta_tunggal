<?php

namespace App\Services;

use App\Models\SuratJalan;

class SuratJalanService
{
    public function generateCode()
    {
        $date = now()->format('Ymd');
        $prefix = 'SJ-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = SuratJalan::where('sj_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
