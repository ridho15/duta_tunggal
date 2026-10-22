<?php

namespace App\Http\Requests\Manufaktur;

use App\Models\ManufacturingOrder;
use App\Models\Production;
use App\Services\ProductionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('production_number'))) {
            $productionService = app(ProductionService::class);
            $this->merge([
                'production_number' => $productionService->generateProductionNumber(),
            ]);
        }

        if (blank($this->input('production_date'))) {
            $this->merge([
                'production_date' => now()->toDateString(),
            ]);
        }

        if (blank($this->input('status'))) {
            $this->merge([
                'status' => 'draft',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'production_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('productions', 'production_number')->whereNull('deleted_at'),
            ],
            'manufacturing_order_id' => [
                'required',
                'integer',
                Rule::exists('manufacturing_orders', 'id'),
            ],
            'quantity_produced' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'production_date' => [
                'required',
                'date',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(['draft', 'finished']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'production_number.required' => 'Nomor bukti produksi wajib diisi.',
            'production_number.unique' => 'Nomor bukti produksi sudah terdaftar di sistem.',
            'manufacturing_order_id.required' => 'Surat Perintah Kerja (SPK) wajib dipilih.',
            'manufacturing_order_id.exists' => 'Surat Perintah Kerja (SPK) tidak valid.',
            'quantity_produced.required' => 'Jumlah kuantitas hasil produksi wajib diisi.',
            'quantity_produced.numeric' => 'Jumlah kuantitas hasil produksi harus berupa angka.',
            'quantity_produced.min' => 'Jumlah kuantitas hasil produksi minimal 0.01.',
            'production_date.required' => 'Tanggal pencatatan produksi wajib diisi.',
            'production_date.date' => 'Format tanggal pencatatan produksi tidak valid.',
        ];
    }
}
