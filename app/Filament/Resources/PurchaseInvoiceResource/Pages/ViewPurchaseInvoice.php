<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
            Actions\Action::make('mark_as_sent')
                ->label('Mark as Sent')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn ($record) => $record->status !== 'sent')
                ->requiresConfirmation()
                ->modalHeading('Mark Invoice as Sent')
                ->modalDescription('Are you sure you want to mark this invoice as sent? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Mark as Sent')
                ->action(function ($record) {
                    $record->update(['status' => 'sent']);
                    \Filament\Notifications\Notification::make()
                        ->title('Invoice marked as sent')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('print_invoice')
                ->label('Cetak Invoice')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->action(function ($record) {
                    $pdf = Pdf::loadView('pdf.purchase-order-invoice-2', [
                        'invoice' => $record
                    ])->setPaper('A4', 'portrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Invoice_PO_' . $record->invoice_number . '.pdf');
                })
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load related temporary/form state so view page shows complete data
        if ($this->record->from_model_type === 'App\Models\PurchaseOrder') {
            $data['selected_supplier'] = $this->record->fromModel->supplier_id ?? null;
            $data['selected_purchase_order'] = $this->record->from_model_id ?? null;
            $data['selected_purchase_receipts'] = $this->record->purchase_receipts ?? [];

            // Load receipt biaya items from purchase receipts so the repeater shows data
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
                    }
                }
            }

            $data['receiptBiayaItems'] = $receiptBiayaItems;
        }

        // Load invoice items from relation so repeater shows saved items
        $invoiceItems = $this->record->invoiceItem()->get()->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
            ];
        })->toArray();

        $data['invoiceItem'] = $invoiceItems;

        // Ensure subtotal/total/other fee are present in state for display
        $data['subtotal'] = $this->record->subtotal ?? ($this->record->invoiceItem()->sum('total') ?? 0);
        $data['other_fee'] = $this->record->other_fee ?? ($this->record->getOtherFeeTotalAttribute() ?? 0);
        $data['total'] = $this->record->total ?? ($data['subtotal'] + ($data['other_fee'] ?? 0));

        // Load COA data from database
        $data['accounts_payable_coa_id'] = $this->record->accounts_payable_coa_id;
        $data['ppn_masukan_coa_id'] = $this->record->ppn_masukan_coa_id;
        $data['inventory_coa_id'] = $this->record->inventory_coa_id;
        $data['expense_coa_id'] = $this->record->expense_coa_id;

        return $data;
    }
}
