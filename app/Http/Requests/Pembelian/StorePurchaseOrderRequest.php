<?php

namespace App\Http\Requests\Pembelian;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'supplier_id' => 'required|exists:suppliers,id',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'po_number' => 'nullable|string|max:100',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date',
            'top_type' => 'nullable|string|in:cod,advance_before_delivery,deposit_balance,credit_days',
            'tempo_hutang' => 'nullable|integer|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0',
            'is_import' => 'nullable|boolean',
            'is_asset' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
            'order_request_id' => 'nullable|exists:order_requests,id',
            'action' => 'nullable|string|in:save_draft,save_and_approve,save_and_request',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.order_request_item_id' => 'nullable|exists:order_request_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.tipe_pajak' => 'nullable|string|in:none,inklusif,eklusif',
            'items.*.currency_id' => 'nullable|exists:currencies,id',
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
            'supplier_id' => 'Supplier',
            'cabang_id' => 'Cabang',
            'warehouse_id' => 'Gudang Tujuan',
            'po_number' => 'Nomor PO',
            'order_date' => 'Tanggal Pesanan',
            'expected_date' => 'Tanggal Estimasi Tiba',
            'top_type' => 'Termin Pembayaran (TOP)',
            'tempo_hutang' => 'Tempo Hutang',
            'currency_id' => 'Mata Uang',
            'exchange_rate' => 'Nilai Kurs',
            'items' => 'Item Barang',
            'items.*.product_id' => 'Produk',
            'items.*.quantity' => 'Kuantitas',
            'items.*.unit_price' => 'Harga Satuan',
            'items.*.discount' => 'Diskon',
            'items.*.tax' => 'Pajak',
            'items.*.tipe_pajak' => 'Tipe Pajak',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak terdaftar di sistem.',
            'order_date.required' => 'Tanggal pesanan wajib diisi.',
            'order_date.date' => 'Format tanggal pesanan tidak valid.',
            'items.required' => 'Minimal harus ada 1 item barang yang dipesan.',
            'items.min' => 'Minimal harus ada 1 item barang yang dipesan.',
            'items.*.product_id.required' => 'Produk pada baris barang wajib dipilih.',
            'items.*.product_id.exists' => 'Produk yang dipilih tidak valid.',
            'items.*.quantity.required' => 'Kuantitas barang wajib diisi.',
            'items.*.quantity.numeric' => 'Kuantitas barang harus berupa angka.',
            'items.*.quantity.min' => 'Kuantitas barang minimal 0.01.',
            'items.*.unit_price.required' => 'Harga satuan barang wajib diisi.',
            'items.*.unit_price.numeric' => 'Harga satuan barang harus berupa angka.',
            'items.*.unit_price.min' => 'Harga satuan minimal 0.',
        ];
    }
}
