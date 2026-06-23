<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Services\PurchaseInvoiceAccountingService;
use App\Support\CurrencyConversionResolver;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->icon('heroicon-o-eye'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
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
            // other_fee can be stored as integer 0 in DB; always ensure it's an array
            $rawOtherFee = $this->record->other_fee;
            $data['other_fees'] = is_array($rawOtherFee) ? $rawOtherFee : [];

            // Load receiptBiayaItems from selected purchase receipts
            $receiptBiayaItems = [];
            if (!empty($data['selected_purchase_receipts'])) {
                $purchaseReceipts = \App\Models\PurchaseReceipt::with('purchaseReceiptBiaya')
                    ->whereIn('id', $data['selected_purchase_receipts'])
                    ->get();

                foreach ($purchaseReceipts as $receipt) {
                    foreach ($receipt->purchaseReceiptBiaya as $biaya) {
                        $receiptBiayaItems[] = [
                            'receipt_id' => $receipt->id,
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

            $isImport = (bool) ($this->record->fromModel?->is_import ?? false);
            if (! $isImport) {
                $data['pph22_amount'] = 0;
                $data['bea_masuk_amount'] = 0;
                $data['receiptBiayaItems'] = array_values(array_filter($data['receiptBiayaItems'], function ($item) {
                    $name = strtolower(trim((string) ($item['nama_biaya'] ?? '')));
                    return ! preg_match('/\bpph\b|pph\s*22|bea masuk|customs|bm|import duty|cukai/', $name);
                }));
                $data['other_fees'] = array_values(array_filter($data['other_fees'], function ($fee) {
                    $name = strtolower(trim((string) ($fee['name'] ?? '')));
                    return ! preg_match('/\bpph\b|pph\s*22|bea masuk|customs|bm|import duty|cukai/', $name);
                }));
            }
        }

        // Load COA data from database
        $data['accounts_payable_coa_id'] = $this->record->accounts_payable_coa_id;
        $data['ppn_masukan_coa_id'] = $this->record->ppn_masukan_coa_id;
        $data['inventory_coa_id'] = $this->record->inventory_coa_id;
        $data['expense_coa_id'] = $this->record->expense_coa_id;

        $data['currency_id'] = $this->record->currency_id ?? $this->record->fromModel?->currency_id;
        $data['exchange_rate'] = (float) ($this->record->exchange_rate ?? CurrencyConversionResolver::resolveRate(is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_supplier']);
        unset($data['selected_purchase_order']);
        unset($data['selected_purchase_receipts']);

        $data = app(PurchaseInvoiceAccountingService::class)->normalizeFormData($data);
        unset($data['other_fees'], $data['receiptBiayaItems']);

        $data['currency_id'] = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
        $data['exchange_rate'] = (float) ($data['exchange_rate'] ?? 1.0);
        
        return $data;
    }

    protected function afterSave(): void
    {
        try {
            if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
                $this->record->invoiceItem()->delete();

                $items = app(PurchaseInvoiceAccountingService::class)->normalizeInvoiceItems($this->data['invoiceItem']);
                foreach ($items as $item) {
                    $this->record->invoiceItem()->create($item);
                }
            }

            app(PurchaseInvoiceAccountingService::class)->finaliseInvoice($this->record);
        } catch (Throwable $exception) {
            Log::error('EditPurchaseInvoice afterSave failed', [
                'invoice_id' => $this->record?->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::warning(
                'Invoice Pembelian Tersimpan Dengan Catatan',
                $exception,
                'Perubahan invoice pembelian berhasil disimpan, tetapi sinkronisasi detail item belum selesai. Periksa kembali invoice ini sebelum dilanjutkan.'
            );
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return PurchaseInvoiceAccountingService::withoutObserverPosting(
                fn () => parent::handleRecordUpdate($record, $data)
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('EditPurchaseInvoice handleRecordUpdate failed', [
                'invoice_id' => $record->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Memperbarui Invoice Pembelian',
                $exception,
                'Perubahan invoice pembelian belum berhasil disimpan. Periksa kembali data invoice lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
