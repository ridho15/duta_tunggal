<?php

namespace App\Http\Requests\Manufaktur;

use Illuminate\Foundation\Http\FormRequest;

class StoreManufacturingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->mo_number)) {
            $this->merge([
                'mo_number' => app(\App\Services\ManufacturingService::class)->generateMoNumber(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'mo_number' => ['required', 'string', 'max:255', 'unique:manufacturing_orders,mo_number'],
            'production_plan_id' => ['nullable', 'exists:production_plans,id'],
            'bill_of_material_id' => ['required_without:production_plan_id', 'nullable', 'exists:bill_of_materials,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'cabang_id' => ['nullable', 'exists:cabangs,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
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
            'bill_of_material_id.required_without' => 'Resep BOM harus dipilih jika tidak memilih Rencana Produksi.',
            'quantity.required' => 'Kuantitas target produksi wajib diisi.',
            'quantity.min' => 'Kuantitas target produksi minimal 0.0001.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ];
    }
}
