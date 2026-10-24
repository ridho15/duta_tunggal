<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', 'unique:warehouses,kode'],
            'name' => ['required', 'string', 'max:255'],
            'cabang_id' => ['required', 'exists:cabangs,id'],
            'location' => ['required', 'string', 'max:500'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'tipe' => ['nullable', 'string', 'in:Kecil,Besar'],
            'status' => ['required', 'boolean'],
            'is_quarantine' => ['nullable', 'boolean'],
            'warna_background' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode gudang wajib diisi.',
            'kode.unique' => 'Kode gudang sudah terdaftar, gunakan kode lain.',
            'name.required' => 'Nama gudang wajib diisi.',
            'cabang_id.required' => 'Kantor cabang wajib dipilih.',
            'cabang_id.exists' => 'Cabang yang dipilih tidak valid.',
            'location.required' => 'Alamat / lokasi gudang wajib diisi.',
            'status.required' => 'Status aktif wajib ditentukan.',
        ];
    }
}
