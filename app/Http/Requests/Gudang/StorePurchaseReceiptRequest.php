<?php

namespace App\Http\Requests\Gudang;

use App\Support\OrderRequestQuantityLock;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReceiptRequest extends FormRequest
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
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'receipt_date' => 'required|date',
            'received_by' => 'nullable|exists:users,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'supplier_delivery_note_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'currency_id' => 'nullable|exists:currencies,id',
            'cabang_id' => 'nullable|exists:cabangs,id',
            'action' => 'nullable|string|in:save_draft,save_and_confirm',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.rak_id' => 'nullable|exists:raks,id',
            'items.*.qty_received' => 'required|numeric|min:0.01',
            'items.*.qty_accepted' => 'nullable|numeric|min:0',
            'items.*.qty_rejected' => 'nullable|numeric|min:0',
            'items.*.reason_rejected' => 'nullable|string|max:500',
            'photos' => 'nullable|array',
            'photos.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'biayas' => 'nullable|array',
            'biayas.*.nama_biaya' => 'required_with:biayas|string|max:255',
            'biayas.*.total' => 'required_with:biayas|numeric|min:0',
            'biayas.*.coa_id' => 'nullable|exists:chart_of_accounts,id',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $poItemId = $item['purchase_order_item_id'] ?? null;
                $qtyReceived = (float) ($item['qty_received'] ?? 0);
                $qtyAccepted = (float) ($item['qty_accepted'] ?? $qtyReceived);
                $qtyRejected = (float) ($item['qty_rejected'] ?? 0);

                if ($poItemId) {
                    $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $poItemId);
                    if ($qtyReceived > $limit['remaining_received']) {
                        $validator->errors()->add(
                            "items.{$index}.qty_received",
                            "Kuantitas diterima ({$qtyReceived}) melebihi sisa PO yang belum diterima ({$limit['remaining_received']})."
                        );
                    }

                    if ($qtyAccepted > $limit['remaining_accepted']) {
                        $validator->errors()->add(
                            "items.{$index}.qty_accepted",
                            "Kuantitas disetujui ({$qtyAccepted}) melebihi sisa PO ({$limit['remaining_accepted']})."
                        );
                    }
                }

                if (round($qtyAccepted + $qtyRejected, 4) > round($qtyReceived, 4)) {
                    $validator->errors()->add(
                        "items.{$index}.qty_accepted",
                        "Total kuantitas disetujui ({$qtyAccepted}) dan ditolak ({$qtyRejected}) tidak boleh melebihi kuantitas diterima ({$qtyReceived})."
                    );
                }

                if ($qtyRejected > 0 && empty($item['reason_rejected'])) {
                    $validator->errors()->add(
                        "items.{$index}.reason_rejected",
                        "Alasan penolakan wajib diisi jika terdapat barang yang ditolak."
                    );
                }
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_order_id' => 'Pesanan Pembelian (PO)',
            'receipt_date' => 'Tanggal Penerimaan',
            'received_by' => 'Petugas Penerima',
            'warehouse_id' => 'Gudang Tujuan',
            'supplier_delivery_note_number' => 'No. Surat Jalan Vendor',
            'notes' => 'Catatan',
            'items' => 'Daftar Barang',
            'items.*.product_id' => 'Produk',
            'items.*.warehouse_id' => 'Gudang Penyimpanan',
            'items.*.rak_id' => 'Rak Gudang',
            'items.*.qty_received' => 'Kuantitas Diterima',
            'items.*.qty_accepted' => 'Kuantitas Disetujui',
            'items.*.qty_rejected' => 'Kuantitas Ditolak',
            'items.*.reason_rejected' => 'Alasan Ditolak',
            'photos.*' => 'Lampiran Foto',
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
            'purchase_order_id.required' => 'Silakan pilih Pesanan Pembelian (PO) yang akan diterima barangnya.',
            'receipt_date.required' => 'Tanggal penerimaan barang wajib diisi.',
            'items.required' => 'Minimal harus ada 1 barang yang dicatat penerimaannya.',
            'items.min' => 'Minimal harus ada 1 barang yang dicatat penerimaannya.',
            'items.*.qty_received.min' => 'Kuantitas diterima minimal 0.01.',
            'photos.*.image' => 'File lampiran harus berupa gambar.',
            'photos.*.max' => 'Ukuran file gambar maksimal 2MB.',
        ];
    }
}
