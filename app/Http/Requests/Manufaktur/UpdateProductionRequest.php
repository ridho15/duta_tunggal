<?php

namespace App\Http\Requests\Manufaktur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productionId = $this->route('production')?->id ?? $this->route('production');

        return [
            'production_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('productions', 'production_number')->whereNull('deleted_at')->ignore($productionId),
            ],
            'quantity_produced' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'production_date' => [
                'required',
                'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'production_number.required' => 'Nomor bukti produksi wajib diisi.',
            'production_number.unique' => 'Nomor bukti produksi sudah terdaftar di sistem.',
            'quantity_produced.required' => 'Jumlah kuantitas hasil produksi wajib diisi.',
            'quantity_produced.numeric' => 'Jumlah kuantitas hasil produksi harus berupa angka.',
            'quantity_produced.min' => 'Jumlah kuantitas hasil produksi minimal 0.01.',
            'production_date.required' => 'Tanggal pencatatan produksi wajib diisi.',
            'production_date.date' => 'Format tanggal pencatatan produksi tidak valid.',
        ];
    }
}
