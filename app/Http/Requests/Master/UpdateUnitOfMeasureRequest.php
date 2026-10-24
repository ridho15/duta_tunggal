<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uom = $this->route('satuan') ?? $this->route('unit_of_measure');
        $uomId = is_object($uom) ? $uom->id : $uom;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('unit_of_measures', 'name')->ignore($uomId),
            ],
            'abbreviation' => [
                'required',
                'string',
                'max:50',
                Rule::unique('unit_of_measures', 'abbreviation')->ignore($uomId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama satuan wajib diisi.',
            'name.unique' => 'Nama satuan sudah digunakan oleh satuan lain.',
            'abbreviation.required' => 'Singkatan / simbol satuan wajib diisi.',
            'abbreviation.unique' => 'Singkatan satuan sudah digunakan oleh satuan lain.',
        ];
    }
}
