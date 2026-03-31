<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseOrder;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['selected_purchase_orders']) && empty($data['selected_order_request'])) {
            throw ValidationException::withMessages([
                'selected_order_request' => 'Order Request harus dipilih terlebih dahulu sebelum memilih Purchase Order.',
            ]);
        }

        // Remove temporary fields
        unset($data['selected_supplier']);
        unset($data['selected_order_request']); // Task 14: remove OR filter field
        
        // Task 14: Move selected POs to purchase_order_ids, remove form temp fields
        $data['purchase_order_ids'] = $data['selected_purchase_orders'] ?? [];

        // Enforce branch inheritance from selected Purchase Order (first PO as source)
        if (!empty($data['purchase_order_ids']) && is_array($data['purchase_order_ids'])) {
            $firstPoId = collect($data['purchase_order_ids'])->filter()->first();
            if ($firstPoId) {
                $purchaseOrder = PurchaseOrder::find($firstPoId);
                if ($purchaseOrder && !empty($purchaseOrder->cabang_id)) {
                    $data['cabang_id'] = $purchaseOrder->cabang_id;
                }
            }
        }

        unset($data['selected_purchase_orders']);
        unset($data['selected_purchase_receipts']);
        
        // Ensure status is set to 'paid' for automatic journal posting
        $data['status'] = $data['status'] ?? 'paid';

        // Ensure COA fields are set with defaults if not provided
        $data['accounts_payable_coa_id'] = $data['accounts_payable_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '2110')->first()?->id;
        $data['ppn_masukan_coa_id'] = $data['ppn_masukan_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '1170.06')->first()?->id;
        $data['inventory_coa_id'] = $data['inventory_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '1140.01')->first()?->id;
        $data['expense_coa_id'] = $data['expense_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '6100.02')->first()?->id;

        $subtotal = (float) \App\Helpers\MoneyHelper::parse($data['subtotal'] ?? 0);
        $data['subtotal'] = $subtotal;
        $ppnRate = (float) ($data['ppn_rate'] ?? 0);
        $ppnAmount = round($subtotal * $ppnRate / 100, 2);
        $data['tax'] = $ppnRate;
        $data['ppn_amount'] = $ppnAmount;

        $otherFees = [];
        if (isset($data['other_fees']) && is_array($data['other_fees'])) {
            $otherFees = array_merge($otherFees, $data['other_fees']);
        }

        if (isset($data['receiptBiayaItems']) && is_array($data['receiptBiayaItems'])) {
            $otherFees = array_merge($otherFees, $data['receiptBiayaItems']);
        }

        $data['other_fee'] = collect($otherFees)->map(function ($fee) {
            $amount = (float) \App\Helpers\MoneyHelper::parse($fee['total'] ?? $fee['amount'] ?? 0);

            if ($amount <= 0) {
                return null;
            }

            return [
                'name' => $fee['nama_biaya'] ?? $fee['name'] ?? 'Biaya Lain',
                'amount' => $amount,
            ];
        })->filter()->values()->toArray();

        unset($data['other_fees'], $data['receiptBiayaItems']);

        // Always parse total in case it comes in as an Indonesian-formatted string (e.g. '17.000.000')
        $parsedTotal = (float) \App\Helpers\MoneyHelper::parse($data['total'] ?? 0);
        if ($parsedTotal === 0.0) {
            $otherFeeTotal = (float) collect($data['other_fee'] ?? [])->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));
            $parsedTotal = $subtotal + $otherFeeTotal + $ppnAmount;
        }
        $data['total'] = $parsedTotal;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            foreach ($this->data['invoiceItem'] as $item) {
                $item['price']    = (float) \App\Helpers\MoneyHelper::parse($item['price'] ?? 0);
                $item['total']    = (float) \App\Helpers\MoneyHelper::parse($item['total'] ?? 0);
                $item['quantity'] = (float) ($item['quantity'] ?? 0);
                $this->record->invoiceItem()->create($item);
            }
        }
    }
}
