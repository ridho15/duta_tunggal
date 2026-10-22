<?php

namespace App\Http\Requests\Manufaktur;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillOfMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (empty($item['uom_id']) && ! empty($item['product_id'])) {
                    $prod = \App\Models\Product::find($item['product_id']);
                    if ($prod && $prod->uom_id) {
                        $items[$index]['uom_id'] = $prod->uom_id;
                    }
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:bill_of_materials,code'],
            'nama_bom' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'uom_id' => ['required', 'exists:unit_of_measures,id'],
            'cabang_id' => ['nullable', 'exists:cabangs,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'work_in_progress_coa_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'labor_coa_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'overhead_coa_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.uom_id' => ['nullable', 'exists:unit_of_measures,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode BOM wajib diisi.',
            'code.unique' => 'Kode BOM sudah digunakan, silakan gunakan kode lain.',
            'nama_bom.required' => 'Nama Resep BOM wajib diisi.',
            'product_id.required' => 'Produk jadi wajib dipilih.',
            'quantity.required' => 'Jumlah hasil produk jadi wajib diisi.',
            'quantity.min' => 'Jumlah hasil produk jadi minimal 0.0001.',
            'uom_id.required' => 'Satuan produk jadi wajib dipilih.',
            'items.required' => 'Komponen bahan baku wajib diisi minimal 1 item.',
            'items.min' => 'Komponen bahan baku wajib diisi minimal 1 item.',
            'items.*.product_id.required' => 'Bahan baku harus dipilih.',
            'items.*.quantity.required' => 'Jumlah kebutuhan bahan baku wajib diisi.',
            'items.*.quantity.min' => 'Jumlah kebutuhan bahan baku minimal 0.0001.',
            'items.*.uom_id.required' => 'Satuan bahan baku wajib dipilih.',
        ];
    }
}
