<?php

namespace App\Http\Requests\Pembelian;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $orderRequest = $this->route('orderRequest') ?? $this->route('permintaan');
        if ($orderRequest && !in_array($orderRequest->status, ['draft', 'rejected'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cabang_id' => 'required|exists:cabangs,id',
            'request_number' => 'nullable|string|max:100',
            'request_date' => 'required|date',
            'required_date' => 'nullable|date',
            'purpose' => 'nullable|string|max:255',
            'currency_id' => 'nullable|exists:currencies,id',
            'note' => 'nullable|string|max:1000',
            'action' => 'nullable|string|in:draft,request_approve',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.cabang_id' => 'nullable|exists:cabangs,id',
            'items.*.supplier_id' => 'nullable|exists:suppliers,id',
            'items.*.currency_id' => 'nullable|exists:currencies,id',
            'items.*.original_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.tipe_pajak' => 'nullable|string|in:none,inklusif,eklusif',
            'items.*.note' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cabang_id' => 'Cabang',
            'request_number' => 'Nomor Permintaan',
            'request_date' => 'Tanggal Permintaan',
            'required_date' => 'Tanggal Dibutuhkan',
            'purpose' => 'Alasan / Keperluan',
            'currency_id' => 'Mata Uang',
            'note' => 'Catatan Tambahan',
            'items' => 'Daftar Barang',
            'items.*.product_id' => 'Produk',
            'items.*.quantity' => 'Kuantitas',
            'items.*.unit_price' => 'Estimasi Harga Satuan',
            'items.*.cabang_id' => 'Cabang Item',
            'items.*.supplier_id' => 'Supplier Rekomendasi',
            'items.*.discount' => 'Diskon Item',
            'items.*.tipe_pajak' => 'Tipe Pajak Item',
            'items.*.tax' => 'Tarif Pajak Item',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cabang_id.required' => 'Cabang pemohon wajib dipilih.',
            'request_date.required' => 'Tanggal permintaan wajib diisi.',
            'items.required' => 'Minimal satu item barang harus ditambahkan.',
            'items.min' => 'Minimal satu item barang harus ditambahkan.',
            'items.*.product_id.required' => 'Produk pada baris barang wajib dipilih.',
            'items.*.product_id.exists' => 'Produk yang dipilih tidak valid.',
            'items.*.quantity.required' => 'Kuantitas barang wajib diisi.',
            'items.*.quantity.min' => 'Kuantitas barang harus lebih besar dari 0.',
        ];
    }
}
