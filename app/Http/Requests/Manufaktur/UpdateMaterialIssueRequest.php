<?php

namespace App\Http\Requests\Manufaktur;

use App\Models\ManufacturingOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('manufacturing_order_id') && empty($this->production_plan_id)) {
            $mo = ManufacturingOrder::find($this->manufacturing_order_id);
            if ($mo && $mo->production_plan_id) {
                $this->merge([
                    'production_plan_id' => $mo->production_plan_id,
                ]);
            }
        }
    }

    public function rules(): array
    {
        $materialIssueId = $this->route('materialIssue') instanceof \App\Models\MaterialIssue
            ? $this->route('materialIssue')->id
            : $this->route('materialIssue');

        return [
            'type' => ['required', 'in:issue,return'],
            'issue_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('material_issues', 'issue_number')->ignore($materialIssueId),
            ],
            'manufacturing_order_id' => ['nullable', 'exists:manufacturing_orders,id'],
            'production_plan_id' => ['nullable', 'exists:production_plans,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'issue_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.uom_id' => ['required', 'exists:unit_of_measures,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'items.*.warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'items.*.rak_id' => ['nullable', 'exists:raks,id'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Tipe transaksi (Ambil/Retur) wajib dipilih.',
            'type.in' => 'Tipe transaksi harus Ambil Barang (issue) atau Retur Barang (return).',
            'issue_number.required' => 'Nomor Bukti Pengeluaran/Retur wajib diisi.',
            'issue_number.unique' => 'Nomor Bukti tersebut sudah digunakan.',
            'warehouse_id.required' => 'Gudang utama wajib dipilih.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak valid.',
            'issue_date.required' => 'Tanggal transaksi wajib diisi.',
            'issue_date.before_or_equal' => 'Tanggal transaksi tidak boleh melampaui hari ini.',
            'items.required' => 'Minimal harus ada 1 item bahan yang dicantumkan.',
            'items.min' => 'Minimal harus ada 1 item bahan yang dicantumkan.',
            'items.*.product_id.required' => 'Produk bahan baku harus dipilih.',
            'items.*.uom_id.required' => 'Satuan bahan baku harus dipilih.',
            'items.*.quantity.required' => 'Jumlah kuantitas bahan wajib diisi.',
            'items.*.quantity.min' => 'Jumlah kuantitas minimal 0.0001.',
        ];
    }
}
