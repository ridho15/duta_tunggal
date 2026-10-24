<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:unit_of_measures,name'],
            'abbreviation' => ['required', 'string', 'max:50', 'unique:unit_of_measures,abbreviation'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama satuan wajib diisi.',
            'name.unique' => 'Nama satuan sudah terdaftar.',
            'abbreviation.required' => 'Singkatan / simbol satuan wajib diisi.',
            'abbreviation.unique' => 'Singkatan satuan sudah terdaftar.',
        ];
    }
}
