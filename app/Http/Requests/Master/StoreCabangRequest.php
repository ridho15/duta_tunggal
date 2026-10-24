<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreCabangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode' => 'required|string|max:50|unique:cabangs,kode',
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:500',
            'telepon' => 'nullable|string|max:50',
            'status' => 'nullable|boolean',
            'kenaikan_harga' => 'nullable|numeric|min:0',
            'kode_invoice_pajak' => 'nullable|string|max:50',
            'kode_invoice_non_pajak' => 'nullable|string|max:50',
            'nama_kwitansi' => 'nullable|string|max:255',
            'nama_legal' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
            'alamat_pajak' => 'nullable|string|max:500',
            'rekening' => 'nullable|array',
        ];
    }

    public function attributes(): array
    {
        return [
            'kode' => 'Kode Cabang',
            'nama' => 'Nama Cabang',
            'alamat' => 'Alamat Cabang',
            'telepon' => 'Nomor Telepon',
            'status' => 'Status Aktif',
            'kenaikan_harga' => 'Persentase Kenaikan Harga',
        ];
    }
}
