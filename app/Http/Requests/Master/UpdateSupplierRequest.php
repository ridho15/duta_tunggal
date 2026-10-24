<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier') instanceof \App\Models\Supplier
            ? $this->route('supplier')->id
            : $this->route('supplier');

        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'code')->ignore($supplierId),
            ],
            'perusahaan' => 'required|string|max:255',
            'kontak_person' => 'nullable|string|max:255',
            'npwp' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'handphone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'fax' => 'nullable|string|max:50',
            'tempo_hutang' => 'required|integer|min:0',
            'nama_bank' => 'nullable|string|max:100',
            'nomor_rekening' => 'nullable|string|max:100',
            'nama_rekening' => 'nullable|string|max:150',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'keterangan' => 'nullable|string|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Kode Supplier',
            'perusahaan' => 'Nama Perusahaan Supplier',
            'kontak_person' => 'Kontak Person',
            'npwp' => 'NPWP',
            'address' => 'Alamat',
            'phone' => 'Nomor Telepon',
            'handphone' => 'Nomor Handphone',
            'email' => 'Alamat Email',
            'fax' => 'Nomor Fax',
            'tempo_hutang' => 'Tempo Hutang (Hari)',
            'nama_bank' => 'Nama Bank',
            'nomor_rekening' => 'Nomor Rekening',
            'nama_rekening' => 'Nama Pemilik Rekening',
            'cabang_id' => 'Cabang',
        ];
    }
}
