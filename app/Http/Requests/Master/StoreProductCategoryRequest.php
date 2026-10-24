<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:product_categories,name'],
            'kode' => ['required', 'string', 'max:50', 'unique:product_categories,kode'],
            'kenaikan_harga' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Nama kategori sudah terdaftar.',
            'kode.required' => 'Kode kategori wajib diisi.',
            'kode.unique' => 'Kode kategori sudah terdaftar.',
            'kenaikan_harga.numeric' => 'Persentase kenaikan harga harus berupa angka.',
            'kenaikan_harga.min' => 'Kenaikan harga minimal 0%.',
            'kenaikan_harga.max' => 'Kenaikan harga maksimal 100%.',
        ];
    }
}
