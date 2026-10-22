<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255|unique:suppliers,code',
            'perusahaan' => 'required|string|max:255',
            'kontak_person' => 'nullable|string|max:255',
            'npwp' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'handphone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'fax' => 'required|string|max:50',
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
