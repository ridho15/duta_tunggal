<?php

namespace App\Services;

use App\Models\ChartOfAccount;

class ChartOfAccountService
{
    public function generateCode()
    {
        $date = now()->format('Ymd');
        $prefix = 'COA-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = ChartOfAccount::where('code', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
