<?php

namespace App\Services;

use App\Models\Customer;

class CustomerService
{
    public function generateCode()
    {
        $date = now()->format('Ymd');
        $prefix = 'CUS-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = Customer::where('code', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
