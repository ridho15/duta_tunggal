<?php

namespace App\Http\Requests\Manufaktur;

use App\Models\Production;
use App\Models\Rak;
use App\Services\QualityControlService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreQualityControlManufactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('qc_number'))) {
            $qcService = app(QualityControlService::class);
            $this->merge([
                'qc_number' => $qcService->generateQcManufactureNumber(),
            ]);
        }

        if (blank($this->input('inspected_by'))) {
            $this->merge([
                'inspected_by' => Auth::id() ?? 1,
            ]);
        }

        if (blank($this->input('rejected_quantity'))) {
            $this->merge([
                'rejected_quantity' => 0,
            ]);
        }
    }

    public function rules(): array
    {
        $warehouseId = $this->input('warehouse_id');

        return [
            'qc_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('quality_controls', 'qc_number')->whereNull('deleted_at'),
            ],
            'from_model_id' => [
                'required',
                'integer',
                Rule::exists('productions', 'id'),
            ],
            'passed_quantity' => [
                'required',
                'numeric',
                'min:0',
            ],
            'rejected_quantity' => [
                'required',
                'numeric',
                'min:0',
            ],
            'reason_reject' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => (float) $this->input('rejected_quantity') > 0),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id'),
            ],
            'rak_id' => [
                'nullable',
                'integer',
                Rule::exists('raks', 'id'),
                function ($attribute, $value, $fail) use ($warehouseId) {
                    if (filled($warehouseId) && Rak::where('warehouse_id', $warehouseId)->exists()) {
                        if (blank($value)) {
                            $fail('Rak penyimpanan barang jadi wajib dipilih untuk gudang yang memiliki konfigurasi rak.');
                        }
                    }
                },
            ],
            'inspected_by' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'qc_number.required' => 'Nomor QC wajib diisi.',
            'qc_number.unique' => 'Nomor QC sudah digunakan.',
            'from_model_id.required' => 'Referensi dokumen produksi wajib dipilih.',
            'from_model_id.exists' => 'Dokumen produksi tidak ditemukan.',
            'warehouse_id.required' => 'Gudang penyimpanan barang jadi wajib dipilih.',
            'inspected_by.required' => 'Petugas inspeksi QC wajib dipilih.',
        ];
    }
}
