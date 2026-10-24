<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_date' => ['required', 'date'],
            'type' => ['required', 'in:PPN,PPH,Custom'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengaturan pajak wajib diisi.',
            'rate.required' => 'Tarif persentase pajak wajib diisi.',
            'rate.numeric' => 'Tarif pajak harus berupa angka.',
            'effective_date.required' => 'Tanggal berlaku pajak wajib diisi.',
            'type.required' => 'Tipe pajak wajib dipilih.',
            'type.in' => 'Tipe pajak harus berupa PPN, PPH, atau Custom.',
            'status.required' => 'Status aktif wajib ditentukan.',
        ];
    }
}
