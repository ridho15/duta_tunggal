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
use Illuminate\Support\Facades\DB;
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

        $service = app(PurchaseInvoiceAccountingService::class);
        $data = $service->validateReceiptBackedCreateData($data);

        if (!empty($data['purchase_order_ids'])) {
            $poCurrencyContext = $service->currencyContextFromPurchaseOrderIds($data['purchase_order_ids']);

            if ($poCurrencyContext !== null) {
                $data['currency_id'] = $poCurrencyContext['currency_id'];
                $data['exchange_rate'] = $poCurrencyContext['exchange_rate'];
            }
        }

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

        $data = $service->normalizeFormData($data);

        unset($data['other_fees'], $data['receiptBiayaItems']);

        $data['currency_id'] = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
        $data['exchange_rate'] = (float) ($data['exchange_rate'] ?? 1.0);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data): Model {
                $service = app(PurchaseInvoiceAccountingService::class);
                $data = $service->validateReceiptBackedCreateData($data, lockForUpdate: true);
                $items = $service->normalizeInvoiceItems($data['invoiceItem'] ?? []);

                unset(
                    $data['selected_supplier'],
                    $data['selected_order_request'],
                    $data['selected_purchase_orders'],
                    $data['selected_purchase_receipts'],
                    $data['invoiceItem'],
                    $data['other_fees'],
                    $data['receiptBiayaItems']
                );

                $record = PurchaseInvoiceAccountingService::withoutObserverPosting(
                    fn () => parent::handleRecordCreation($data)
                );

                foreach ($items as $item) {
                    $record->invoiceItem()->create($item);
                }

                return $service->finaliseInvoice($record);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('CreatePurchaseInvoice handleRecordCreation failed', [
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Membuat Invoice Pembelian',
                null,
                'Invoice pembelian belum berhasil dibuat. Periksa kembali data invoice lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
