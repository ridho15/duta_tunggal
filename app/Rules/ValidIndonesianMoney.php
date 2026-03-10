<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Helpers\MoneyHelper;

class ValidIndonesianMoney implements Rule
{
    /**
     * Determine if the validation rule passes.
     * We accept formatted Indonesian money strings or plain numbers.
     * The parsed numeric value must be >= 1.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $parsed = MoneyHelper::parse($value);
        if (!is_numeric($parsed)) {
            return false;
        }
        return $parsed >= 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Jumlah deposit harus berupa angka positif minimal 1.';
    }
}
