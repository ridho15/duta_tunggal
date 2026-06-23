<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a repeater's items array has no duplicate product_id values.
 *
 * Usage in a Filament repeater ->rules([new NoDuplicateProducts()]) or
 * in standard Laravel validation as 'items' => [new NoDuplicateProducts()].
 */
class NoDuplicateProducts implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $productIds = array_filter(
            array_column($value, 'product_id'),
            fn ($id) => !is_null($id) && $id !== ''
        );

        $duplicates = array_keys(
            array_filter(
                array_count_values(array_map('strval', $productIds)),
                fn ($count) => $count > 1
            )
        );

        if (!empty($duplicates)) {
            $fail('Produk yang sama tidak boleh ditambahkan lebih dari satu kali dalam satu pesanan.');
        }
    }
}
