<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rak = $this->route('rak');
        $rakId = is_object($rak) ? $rak->id : $rak;
        $warehouseId = $this->input('warehouse_id') ?? (is_object($rak) ? $rak->warehouse_id : null);

        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('raks', 'code')
                    ->where(fn ($q) => $q->where('warehouse_id', $warehouseId))
                    ->ignore($rakId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Gudang penempatan rak wajib dipilih.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak valid.',
            'code.required' => 'Kode rak wajib diisi.',
            'code.unique' => 'Kode rak sudah digunakan di gudang ini.',
            'name.required' => 'Nama / nomor rak wajib diisi.',
        ];
    }
}
