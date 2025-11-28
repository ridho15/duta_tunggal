<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load related data for form
        if ($this->record->from_model_type === 'App\Models\PurchaseOrder') {
            $data['selected_supplier'] = $this->record->fromModel->supplier_id ?? null;
            $data['selected_purchase_order'] = $this->record->from_model_id ?? null;
            $data['selected_purchase_receipts'] = $this->record->purchase_receipts ?? [];

            // Load invoiceItem from database
            $invoiceItems = $this->record->invoiceItem()->get()->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                ];
            })->toArray();
            $data['invoiceItem'] = $invoiceItems;

            // Load other_fees from database
            $data['other_fees'] = $this->record->other_fee ?? [];

            // Load receiptBiayaItems from selected purchase receipts
            $receiptBiayaItems = [];
            if (!empty($data['selected_purchase_receipts'])) {
                $purchaseReceipts = \App\Models\PurchaseReceipt::with('purchaseReceiptBiaya')
                    ->whereIn('id', $data['selected_purchase_receipts'])
                    ->get();

                foreach ($purchaseReceipts as $receipt) {
                    foreach ($receipt->purchaseReceiptBiaya as $biaya) {
                        $receiptBiayaItems[] = [
                            'nama_biaya' => $biaya->nama_biaya,
                            'total' => $biaya->total,
                        ];
                        // Add to other_fees if not already present
                        $existingFee = collect($data['other_fees'])->firstWhere('name', $biaya->nama_biaya);
                        if (!$existingFee) {
                            $data['other_fees'][] = [
                                'name' => $biaya->nama_biaya,
                                'amount' => $biaya->total,
                            ];
                        }
                    }
                }
            }
            $data['receiptBiayaItems'] = $receiptBiayaItems;
        }

        // Load COA data from database
        $data['accounts_payable_coa_id'] = $this->record->accounts_payable_coa_id;
        $data['ppn_masukan_coa_id'] = $this->record->ppn_masukan_coa_id;
        $data['inventory_coa_id'] = $this->record->inventory_coa_id;
        $data['expense_coa_id'] = $this->record->expense_coa_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_supplier']);
        unset($data['selected_purchase_order']);
        unset($data['selected_purchase_receipts']);
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Sync invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            // Delete existing items
            $this->record->invoiceItem()->delete();
            
            // Create new items
            foreach ($this->data['invoiceItem'] as $item) {
                $this->record->invoiceItem()->create($item);
            }
        }
    }
}
