<?php

namespace App\Services;

use App\Models\ProductCategory;

class ProductCategoryService
{
    public function generateKodeKategori()
    {
        $date = now()->format('Ymd');
        $prefix = 'PC-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = ProductCategory::where('kode', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}