<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_supplier']);
        unset($data['selected_order_request']); // Task 14: remove OR filter field
        
        // Task 14: Move selected POs to purchase_order_ids, remove form temp fields
        $data['purchase_order_ids'] = $data['selected_purchase_orders'] ?? [];
        unset($data['selected_purchase_orders']);
        unset($data['selected_purchase_receipts']);
        
        // Ensure status is set to 'paid' for automatic journal posting
        $data['status'] = $data['status'] ?? 'paid';

        // Ensure COA fields are set with defaults if not provided
        $data['accounts_payable_coa_id'] = $data['accounts_payable_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '2110')->first()?->id;
        $data['ppn_masukan_coa_id'] = $data['ppn_masukan_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '1170.06')->first()?->id;
        $data['inventory_coa_id'] = $data['inventory_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '1140.01')->first()?->id;
        $data['expense_coa_id'] = $data['expense_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '6100.02')->first()?->id;
        $data['other_fee'] = $data['other_fees'] ?? [];
        return $data;
    }

    protected function afterCreate(): void
    {
        // Create invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            foreach ($this->data['invoiceItem'] as $item) {
                $this->record->invoiceItem()->create($item);
            }
        }
    }
}
