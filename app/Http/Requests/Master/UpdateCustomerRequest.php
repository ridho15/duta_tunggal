<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer') instanceof \App\Models\Customer
            ? $this->route('customer')->id
            : $this->route('customer');

        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers', 'code')->ignore($customerId),
            ],
            'name' => 'required|string|max:255',
            'perusahaan' => 'required|string|max:255',
            'nik_npwp' => 'required|string|max:255',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'address' => 'required|string|max:255',
            'telephone' => 'required|string|max:50',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'fax' => 'required|string|max:50',
            'tipe' => 'required|in:PKP,PRI',
            'tipe_pembayaran' => 'required|in:Bebas,COD (Bayar Lunas),Kredit',
            'tempo_kredit' => 'required|integer|min:0',
            'kredit_limit' => 'required|numeric|min:0',
            'isSpecial' => 'nullable|boolean',
            'keterangan' => 'nullable|string|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Kode Customer',
            'name' => 'Nama Customer',
            'perusahaan' => 'Nama Perusahaan',
            'nik_npwp' => 'NIK / NPWP',
            'cabang_id' => 'Cabang',
            'address' => 'Alamat',
            'telephone' => 'Nomor Telepon',
            'phone' => 'Nomor Handphone',
            'email' => 'Alamat Email',
            'fax' => 'Nomor Fax',
            'tipe' => 'Tipe Customer (PKP/PRI)',
            'tipe_pembayaran' => 'Tipe Pembayaran',
            'tempo_kredit' => 'Tempo Kredit (Hari)',
            'kredit_limit' => 'Kredit Limit (Rp)',
        ];
    }
}
