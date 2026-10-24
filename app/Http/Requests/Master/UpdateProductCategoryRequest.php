<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('kategori') ?? $this->route('product_category');
        $categoryId = is_object($category) ? $category->id : $category;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')->ignore($categoryId),
            ],
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('product_categories', 'kode')->ignore($categoryId),
            ],
            'kenaikan_harga' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Nama kategori sudah digunakan oleh kategori lain.',
            'kode.required' => 'Kode kategori wajib diisi.',
            'kode.unique' => 'Kode kategori sudah digunakan oleh kategori lain.',
            'kenaikan_harga.numeric' => 'Persentase kenaikan harga harus berupa angka.',
            'kenaikan_harga.min' => 'Kenaikan harga minimal 0%.',
            'kenaikan_harga.max' => 'Kenaikan harga maksimal 100%.',
        ];
    }
}
