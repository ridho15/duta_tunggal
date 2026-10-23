<?php

namespace App\Http\Requests\Asset;

use App\Helpers\MoneyHelper;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Sanitize purchase_cost
        $purchaseCost = $this->input('purchase_cost');
        if ($purchaseCost !== null && $purchaseCost !== '') {
            $purchaseCost = MoneyHelper::parse($purchaseCost);
            $this->merge(['purchase_cost' => $purchaseCost]);
        }

        // Sanitize or auto-calculate salvage_value (5% of purchase_cost if empty or zero)
        $salvageValue = $this->input('salvage_value');
        if ($salvageValue !== null && $salvageValue !== '') {
            $salvageValue = MoneyHelper::parse($salvageValue);
        } else {
            $salvageValue = (float) ($purchaseCost ?? 0) * 0.05;
        }
        $this->merge(['salvage_value' => $salvageValue]);

        // Default code generator if empty
        if (blank($this->input('code'))) {
            $this->merge(['code' => Asset::generateAssetCode()]);
        }

        // Default dates
        if (blank($this->input('purchase_date'))) {
            $this->merge(['purchase_date' => now()->toDateString()]);
        }

        if (blank($this->input('usage_date'))) {
            $this->merge(['usage_date' => $this->input('purchase_date') ?? now()->toDateString()]);
        }

        // Default status
        if (blank($this->input('status'))) {
            $this->merge(['status' => 'active']);
        }

        // Default useful_life_years
        if (blank($this->input('useful_life_years'))) {
            $this->merge(['useful_life_years' => 5]);
        }

        // Default depreciation_method
        if (blank($this->input('depreciation_method'))) {
            $this->merge(['depreciation_method' => 'straight_line']);
        }
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('assets', 'code')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cabang_id' => ['required', 'integer', Rule::exists('cabangs', 'id')],
            'purchase_date' => ['required', 'date'],
            'usage_date' => ['required', 'date'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'purchase_order_item_id' => ['nullable', 'integer', Rule::exists('purchase_order_items', 'id')],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'salvage_value' => ['required', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:50'],
            'depreciation_method' => [
                'required',
                'string',
                Rule::in(['straight_line', 'declining_balance', 'sum_of_years_digits', 'units_of_production']),
            ],
            'asset_coa_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')],
            'accumulated_depreciation_coa_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')],
            'depreciation_expense_coa_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')],
            'credit_coa_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(['active', 'disposed', 'fully_depreciated', 'posted'])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode aset wajib diisi.',
            'code.unique' => 'Kode aset sudah digunakan.',
            'name.required' => 'Nama barang / aset wajib diisi.',
            'cabang_id.required' => 'Cabang wajib dipilih.',
            'cabang_id.exists' => 'Cabang yang dipilih tidak valid.',
            'purchase_date.required' => 'Tanggal perolehan / beli wajib diisi.',
            'usage_date.required' => 'Tanggal mulai pakai wajib diisi.',
            'purchase_cost.required' => 'Biaya perolehan aset wajib diisi.',
            'purchase_cost.numeric' => 'Biaya perolehan aset harus berupa nominal angka.',
            'purchase_cost.min' => 'Biaya perolehan minimal Rp 0.',
            'salvage_value.required' => 'Nilai sisa (salvage value) wajib diisi.',
            'useful_life_years.required' => 'Umur manfaat aset wajib diisi.',
            'useful_life_years.min' => 'Umur manfaat minimal 1 tahun.',
            'useful_life_years.max' => 'Umur manfaat maksimal 50 tahun.',
            'depreciation_method.required' => 'Metode penyusutan wajib dipilih.',
            'asset_coa_id.required' => 'Akun COA Aset Tetap wajib dipilih.',
            'accumulated_depreciation_coa_id.required' => 'Akun COA Akumulasi Penyusutan wajib dipilih.',
            'depreciation_expense_coa_id.required' => 'Akun COA Beban Penyusutan wajib dipilih.',
        ];
    }
}
