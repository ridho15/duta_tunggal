<?php

namespace App\Http\Requests\Manufaktur;

use App\Models\Production;
use App\Models\QualityControl;
use App\Models\Rak;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProcessQualityControlManufactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $qc = $this->route('qc') ?? $this->route('quality_control') ?? $this->route('qualityControl');
            if (is_numeric($qc)) {
                $qc = QualityControl::find($qc);
            }

            if ($qc && $qc->from_model_type === Production::class) {
                $production = $qc->production ?? $qc->fromModel;
                $targetQuantity = (float) ($production?->quantity_produced ?? $production?->manufacturingOrder?->productionPlan?->quantity ?? 0);
                $passed = (float) $this->input('passed_quantity', 0);
                $rejected = (float) $this->input('rejected_quantity', 0);

                if ($targetQuantity > 0 && ($passed + $rejected) > $targetQuantity) {
                    $validator->errors()->add(
                        'passed_quantity',
                        "Total kuantitas lulus ({$passed}) + ditolak ({$rejected}) tidak boleh melebihi kuantitas produksi ({$targetQuantity})."
                    );
                }

                if (($passed + $rejected) <= 0) {
                    $validator->errors()->add(
                        'passed_quantity',
                        'Total kuantitas yang diperiksa (lulus + ditolak) harus lebih besar dari 0.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'passed_quantity.required' => 'Jumlah kuantitas lolos QC wajib diisi.',
            'passed_quantity.numeric' => 'Jumlah kuantitas lolos QC harus berupa angka.',
            'passed_quantity.min' => 'Jumlah kuantitas lolos QC tidak boleh negatif.',
            'rejected_quantity.required' => 'Jumlah kuantitas ditolak/reject wajib diisi.',
            'rejected_quantity.numeric' => 'Jumlah kuantitas ditolak/reject harus berupa angka.',
            'rejected_quantity.min' => 'Jumlah kuantitas ditolak/reject tidak boleh negatif.',
            'reason_reject.required' => 'Alasan penolakan / reject wajib diisi jika terdapat kuantitas yang ditolak.',
            'warehouse_id.required' => 'Gudang penyimpanan barang jadi wajib dipilih.',
            'warehouse_id.exists' => 'Gudang penyimpanan tidak valid.',
            'inspected_by.required' => 'Petugas inspeksi QC wajib dipilih.',
            'inspected_by.exists' => 'Petugas inspeksi QC tidak valid.',
        ];
    }
}
