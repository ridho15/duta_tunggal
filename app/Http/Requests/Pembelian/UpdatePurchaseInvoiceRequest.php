<?php

namespace App\Http\Requests\Pembelian;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseInvoiceRequest extends FormRequest
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
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'tax_invoice_number' => 'nullable|string|max:100',
            'other_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'action' => 'nullable|string|in:update_draft,update_and_post',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.001',
            'items.*.price' => 'required_with:items|numeric|min:0',
            'items.*.po_price' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_date.required' => 'Tanggal Faktur wajib diisi.',
            'invoice_date.date' => 'Format Tanggal Faktur tidak valid.',
            'due_date.required' => 'Tanggal Jatuh Tempo wajib diisi.',
            'due_date.after_or_equal' => 'Tanggal Jatuh Tempo harus sama atau setelah Tanggal Faktur.',
            'other_fee.numeric' => 'Biaya lain / pengiriman harus berupa angka.',
            'other_fee.min' => 'Biaya lain / pengiriman tidak boleh bernilai negatif.',
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
            'invoice_date' => 'Tanggal Faktur',
            'due_date' => 'Tanggal Jatuh Tempo',
            'supplier_invoice_number' => 'Nomor Faktur Pemasok',
            'tax_invoice_number' => 'Nomor Faktur Pajak',
            'other_fee' => 'Biaya Lain / Ongkir',
        ];
    }
}
