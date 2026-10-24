<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'license' => ['nullable', 'string', 'max:100'],
            'cabang_id' => ['nullable', 'exists:cabangs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama driver / supir wajib diisi.',
            'cabang_id.exists' => 'Cabang yang dipilih tidak valid.',
        ];
    }
}
