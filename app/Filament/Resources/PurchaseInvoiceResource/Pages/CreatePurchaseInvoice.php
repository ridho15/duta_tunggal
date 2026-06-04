<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Models\Invoice;
use App\Services\PurchaseInvoiceAccountingService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        unset($data['selected_supplier']);
        unset($data['selected_order_request']); // Task 14: remove OR filter field
        
        // Task 14: Move selected POs to purchase_order_ids, remove form temp fields
        $data['purchase_order_ids'] = $data['selected_purchase_orders'] ?? [];

        if (!empty($data['purchase_order_ids'])) {
            $poCurrencyContext = app(PurchaseInvoiceAccountingService::class)
                ->currencyContextFromPurchaseOrderIds($data['purchase_order_ids']);

            if ($poCurrencyContext !== null) {
                $data['currency_id'] = $poCurrencyContext['currency_id'];
                $data['exchange_rate'] = $poCurrencyContext['exchange_rate'];
            }
        }

        unset($data['selected_purchase_orders']);
        unset($data['selected_purchase_receipts']);
        
        if (! PurchaseInvoiceResource::canManuallySetStatus()) {
            $data['status'] = Invoice::STATUS_DRAFT;
        } else {
            $data['status'] = $data['status'] ?? Invoice::STATUS_DRAFT;
        }

        // Ensure COA fields are set with defaults if not provided
        $data['accounts_payable_coa_id'] = $data['accounts_payable_coa_id'] ?? \App\Models\ChartOfAccount::where('code', config('coa.accounts_payable', '2110'))->first()?->id;
        $data['ppn_masukan_coa_id'] = $data['ppn_masukan_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '1170.06')->first()?->id;
        $data['inventory_coa_id'] = $data['inventory_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '1140.01')->first()?->id;
        $data['expense_coa_id'] = $data['expense_coa_id'] ?? \App\Models\ChartOfAccount::where('code', '6100.02')->first()?->id;

        $data = app(PurchaseInvoiceAccountingService::class)->normalizeFormData($data);

        unset($data['other_fees'], $data['receiptBiayaItems']);

        $data['currency_id'] = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
        $data['exchange_rate'] = (float) ($data['exchange_rate'] ?? 1.0);

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
                $items = app(PurchaseInvoiceAccountingService::class)->normalizeInvoiceItems($this->data['invoiceItem']);
                foreach ($items as $item) {
                    $this->record->invoiceItem()->create($item);
                }
            }

            app(PurchaseInvoiceAccountingService::class)->finaliseInvoice($this->record);
        } catch (Throwable $exception) {
            Log::error('CreatePurchaseInvoice afterCreate failed', [
                'invoice_id' => $this->record?->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::warning(
                'Invoice Pembelian Tersimpan Dengan Catatan',
                $exception,
                'Invoice pembelian berhasil dibuat, tetapi detail item belum berhasil disimpan seluruhnya. Periksa kembali invoice ini sebelum dilanjutkan.'
            );
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return PurchaseInvoiceAccountingService::withoutObserverPosting(
                fn () => parent::handleRecordCreation($data)
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('CreatePurchaseInvoice handleRecordCreation failed', [
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Membuat Invoice Pembelian',
                $exception,
                'Invoice pembelian belum berhasil dibuat. Periksa kembali data invoice lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
