<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function canManageStatus(): bool
    {
        return PurchaseInvoiceResource::canManuallySetStatus();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil')
                ->visible(fn ($record) => $record->status === \App\Models\Invoice::STATUS_DRAFT),
            Actions\Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-book-open')
                ->color('success')
                ->action(function ($record) {
                    $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
                        ->where('source_id', $record->id)
                        ->get();

                    if ($journalEntries->count() === 1) {
                        // Jika hanya 1 journal entry, langsung ke halaman detail
                        $entry = $journalEntries->first();
                        return redirect()->to("/admin/journal-entries/{$entry->id}");
                    } else {
                        // Jika multiple entries, gunakan filter dengan format yang sesuai dengan filter options
                        $sourceType = 'App\\Models\\Invoice'; // Format yang sama dengan filter options
                        $sourceId = $record->id;
                        return redirect()->to("/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}");
                    }
                }),
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn ($record) => $record->status === \App\Models\Invoice::STATUS_DRAFT),
            Actions\Action::make('post_invoice')
                ->label('Posting Invoice')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status === \App\Models\Invoice::STATUS_DRAFT)
                ->requiresConfirmation()
                ->modalHeading('Posting Invoice Pembelian')
                ->modalDescription('Apakah Anda yakin ingin memposting invoice ini? Tindakan ini akan membentuk Hutang Usaha (Account Payable) dan memposting jurnal ke Buku Besar.')
                ->modalSubmitActionLabel('Ya, Posting Invoice')
                ->action(function ($record) {
                    try {
                        app(\App\Services\PurchaseInvoiceAccountingService::class)->postAndApproveInvoice($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Invoice Berhasil Diposting')
                            ->body('Hutang dan jurnal telah berhasil dibukukan.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Log::error('ViewPurchaseInvoice post_invoice failed', [
                            'invoice_id' => $record->id,
                            'error' => $exception->getMessage(),
                        ]);

                        ProcurementFailureNotifier::danger(
                            'Gagal Memposting Invoice',
                            $exception,
                            'Invoice pembelian belum berhasil diposting. Silakan coba lagi.'
                        );
                    }
                }),
            Actions\Action::make('print_invoice')
                ->label('Preview Invoice')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->url(fn($record) => route('pdf-stream', ['type' => 'purchase-invoice', 'id' => $record->id]))
                ->openUrlInNewTab(),
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
