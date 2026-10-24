<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:currencies,code'],
            'symbol' => ['required', 'string', 'max:10'],
            'to_rupiah' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama mata uang wajib diisi.',
            'code.required' => 'Kode ISO mata uang (misal: IDR, USD) wajib diisi.',
            'code.unique' => 'Kode mata uang sudah terdaftar.',
            'symbol.required' => 'Simbol mata uang (misal: Rp, $) wajib diisi.',
            'to_rupiah.required' => 'Nilai kurs konversi ke Rupiah wajib diisi.',
            'to_rupiah.numeric' => 'Nilai kurs harus berupa angka.',
            'to_rupiah.min' => 'Nilai kurs minimal lebih dari 0.',
        ];
    }
}
