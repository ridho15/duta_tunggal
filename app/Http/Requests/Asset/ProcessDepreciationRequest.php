<?php

namespace App\Http\Requests\Asset;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessDepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $year = (int) ($this->input('period_year') ?: now()->year);
        $month = (int) ($this->input('period_month') ?: now()->month);

        $this->merge([
            'period_year' => $year,
            'period_month' => $month,
        ]);

        if (blank($this->input('depreciation_date'))) {
            $date = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
            $this->merge(['depreciation_date' => $date]);
        }
    }

    public function rules(): array
    {
        return [
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2020,2050'],
            'depreciation_date' => ['required', 'date'],
            'cabang_id' => ['nullable', 'integer', Rule::exists('cabangs', 'id')],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'period_month.required' => 'Bulan periode penyusutan wajib dipilih.',
            'period_month.between' => 'Bulan harus antara 1 sampai 12.',
            'period_year.required' => 'Tahun periode penyusutan wajib diisi.',
            'period_year.between' => 'Tahun periode penyusutan tidak valid.',
            'depreciation_date.required' => 'Tanggal pencatatan depresiasi wajib diisi.',
            'cabang_id.exists' => 'Cabang yang dipilih tidak valid.',
            'asset_id.exists' => 'Aset yang dipilih tidak ditemukan.',
        ];
    }
}
