<?php

namespace App\Http\Requests\Manufaktur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManufacturingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mo = $this->route('manufacturingOrder');
        if (empty($this->mo_number) && $mo) {
            $this->merge([
                'mo_number' => is_object($mo) ? $mo->mo_number : $mo,
            ]);
        }
    }

    public function rules(): array
    {
        $mo = $this->route('manufacturingOrder');
        $moId = is_object($mo) ? $mo->id : $mo;

        return [
            'mo_number' => ['required', 'string', 'max:255', Rule::unique('manufacturing_orders', 'mo_number')->ignore($moId)],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'cabang_id' => ['nullable', 'exists:cabangs,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.uom_id' => ['required_with:items', 'exists:unit_of_measures,id'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'mo_number.required' => 'Nomor SPK / MO wajib diisi.',
            'mo_number.unique' => 'Nomor SPK / MO sudah digunakan.',
            'quantity.required' => 'Kuantitas target produksi wajib diisi.',
            'quantity.min' => 'Kuantitas target produksi minimal 0.0001.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ];
    }
}
