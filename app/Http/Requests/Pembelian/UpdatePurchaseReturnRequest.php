<?php

namespace App\Http\Requests\Pembelian;

use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['nullable', 'string', 'in:save_draft,save_and_submit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_receipt_item_id' => ['required', 'exists:purchase_receipt_items,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty_returned' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'delivery_note' => ['nullable', 'string', 'max:100'],
            'shipping_details' => ['nullable', 'string', 'max:255'],
            'physical_return_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'return_date.required' => 'Tanggal retur wajib diisi.',
            'items.required' => 'Pilih minimal satu item barang untuk diretur.',
            'items.min' => 'Pilih minimal satu item barang untuk diretur.',
            'items.*.qty_returned.required' => 'Kuantitas retur wajib diisi.',
            'items.*.qty_returned.min' => 'Kuantitas retur minimal 0.01.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var PurchaseReturn $currentReturn */
            $currentReturn = $this->route('return');
            $currentReturnId = $currentReturn?->id;

            $items = (array) $this->input('items', []);
            if (empty($items)) {
                return;
            }

            foreach ($items as $idx => $itemData) {
                $receiptItemId = $itemData['purchase_receipt_item_id'] ?? null;
                $qtyReturned = (float) ($itemData['qty_returned'] ?? 0);

                if (!$receiptItemId || $qtyReturned <= 0) {
                    continue;
                }

                $receiptItem = PurchaseReceiptItem::with('product')->find($receiptItemId);
                if (!$receiptItem) {
                    $validator->errors()->add("items.{$idx}.purchase_receipt_item_id", "Barang penerimaan tidak ditemukan.");
                    continue;
                }

                $maxAccepted = (float) ($receiptItem->qty_accepted ?? $receiptItem->qty_received ?? 0);

                // Hitung kuantitas yang sudah pernah diretur oleh retur lain
                $alreadyReturned = (float) PurchaseReturnItem::where('purchase_receipt_item_id', $receiptItemId)
                    ->when($currentReturnId, fn ($q) => $q->where('purchase_return_id', '!=', $currentReturnId))
                    ->whereHas('purchaseReturn', function ($q) {
                        $q->whereNotIn('status', ['rejected']);
                    })
                    ->sum('qty_returned');

                $maxReturnable = max(0.0, round($maxAccepted - $alreadyReturned, 4));

                if ($qtyReturned > ($maxReturnable + 0.0001)) {
                    $productName = $receiptItem->product?->name ?? 'Produk';
                    $validator->errors()->add(
                        "items.{$idx}.qty_returned",
                        "Kuantitas retur untuk {$productName} ({$qtyReturned}) melebihi sisa yang dapat diretur ({$maxReturnable})."
                    );
                }
            }
        });
    }
}
