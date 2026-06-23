<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InternationalPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $phone = trim((string) $value);

        if (! preg_match('/^[0-9+\s().-]+$/', $phone)) {
            $fail('Nomor telepon hanya boleh berisi angka, +, spasi, titik, tanda kurung, atau tanda hubung.');
            return;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 6 || strlen($digits) > 20) {
            $fail('Nomor telepon harus berisi 6 sampai 20 digit.');
        }
    }
}
