<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currency = $this->route('mata_uang') ?? $this->route('currency');
        $currencyId = is_object($currency) ? $currency->id : $currency;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('currencies', 'code')->ignore($currencyId),
            ],
            'symbol' => ['required', 'string', 'max:10'],
            'to_rupiah' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama mata uang wajib diisi.',
            'code.required' => 'Kode ISO mata uang wajib diisi.',
            'code.unique' => 'Kode mata uang sudah digunakan pada mata uang lain.',
            'symbol.required' => 'Simbol mata uang wajib diisi.',
            'to_rupiah.required' => 'Nilai kurs konversi ke Rupiah wajib diisi.',
            'to_rupiah.numeric' => 'Nilai kurs harus berupa angka.',
        ];
    }
}
