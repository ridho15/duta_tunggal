<?php

namespace App\Http\Requests\Asset;

use App\Helpers\MoneyHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
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

        // Sanitize salvage_value
        $salvageValue = $this->input('salvage_value');
        if ($salvageValue !== null && $salvageValue !== '') {
            $salvageValue = MoneyHelper::parse($salvageValue);
        } else {
            $salvageValue = (float) ($purchaseCost ?? 0) * 0.05;
        }
        $this->merge(['salvage_value' => $salvageValue]);
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        $assetId = is_object($asset) ? $asset->id : $asset;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('assets', 'code')->ignore($assetId)->whereNull('deleted_at'),
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
            'purchase_date.required' => 'Tanggal perolehan / beli wajib diisi.',
            'usage_date.required' => 'Tanggal mulai pakai wajib diisi.',
            'purchase_cost.required' => 'Biaya perolehan aset wajib diisi.',
            'useful_life_years.required' => 'Umur manfaat aset wajib diisi.',
            'depreciation_method.required' => 'Metode penyusutan wajib dipilih.',
            'asset_coa_id.required' => 'Akun COA Aset Tetap wajib dipilih.',
            'accumulated_depreciation_coa_id.required' => 'Akun COA Akumulasi Penyusutan wajib dipilih.',
            'depreciation_expense_coa_id.required' => 'Akun COA Beban Penyusutan wajib dipilih.',
        ];
    }
}
