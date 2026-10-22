<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product') instanceof \App\Models\Product
            ? $this->route('product')->id
            : $this->route('product');

        return [
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'name' => 'required|string|max:255',
            'product_category_id' => 'required|exists:product_categories,id',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'biaya' => 'nullable|numeric|min:0',
            'harga_batas' => 'nullable|numeric|min:0',
            'item_value' => 'nullable|numeric|min:0',
            'tipe_pajak' => 'required|in:Non Pajak,Inklusif,Eksklusif',
            'pajak' => 'nullable|numeric|min:0|max:100',
            'jumlah_kelipatan_gudang_besar' => 'nullable|integer|min:0',
            'jumlah_jual_kategori_banyak' => 'nullable|integer|min:0',
            'kode_merk' => 'required|string|max:50',
            'uom_id' => 'required|exists:unit_of_measures,id',
            'description' => 'nullable|string|max:2000',
            'is_manufacture' => 'nullable|boolean',
            'is_raw_material' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'inventory_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'sales_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'sales_return_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'sales_discount_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'goods_delivery_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'cogs_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'purchase_return_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'unbilled_purchase_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'temporary_procurement_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'manufacturing_labor_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'manufacturing_overhead_coa_id' => 'nullable|exists:chart_of_accounts,id',
            'unit_conversions' => 'nullable|array',
            'unit_conversions.*.id' => 'nullable|integer',
            'unit_conversions.*.uom_id' => 'required_with:unit_conversions|exists:unit_of_measures,id',
            'unit_conversions.*.nilai_konversi' => 'required_with:unit_conversions|numeric|min:0.0001',
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'name' => 'Nama Produk',
            'product_category_id' => 'Kategori Produk',
            'cabang_id' => 'Cabang',
            'cost_price' => 'Harga Beli Asli',
            'sell_price' => 'Harga Jual',
            'biaya' => 'Biaya',
            'harga_batas' => 'Harga Batas',
            'tipe_pajak' => 'Tipe Pajak',
            'pajak' => 'Persentase Pajak',
            'kode_merk' => 'Kode Merk',
            'uom_id' => 'Satuan (UOM)',
            'description' => 'Deskripsi',
        ];
    }
}
