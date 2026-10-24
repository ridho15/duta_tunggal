<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $coaId = $this->route('coa')?->id ?? $this->route('coa');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('chart_of_accounts', 'code')->ignore($coaId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:Asset,Liability,Equity,Revenue,Expense,Contra Asset',
            'parent_id' => [
                'nullable',
                'exists:chart_of_accounts,id',
                Rule::notIn([$coaId]), // Mencegah akun menjadi induk dirinya sendiri
            ],
            'is_cash_bank' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'opening_balance' => 'nullable|numeric',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Nomor Akun',
            'name' => 'Nama Akun',
            'type' => 'Klasifikasi / Tipe Akun',
            'parent_id' => 'Akun Induk (Parent)',
            'is_cash_bank' => 'Penanda Kas / Bank',
            'is_active' => 'Status Aktif',
            'opening_balance' => 'Saldo Awal',
            'description' => 'Keterangan',
        ];
    }
}
