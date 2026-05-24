<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\OrderRequest;
use App\Services\PurchaseOrderService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static ?string $title = 'Buat Pembelian';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['status']     = 'draft'; // PO dimulai dari draft, perlu disetujui manual
        return PurchaseOrderResource::syncPurchaseOrderCurrencyData($data);
    }

    protected function afterCreate()
    {
        $record = $this->getRecord();

        try {
            $purchaseOrderService = app(PurchaseOrderService::class);
            $purchaseOrderService->updateTotalAmount($record);

            if (
                $record->refer_model_type === OrderRequest::class
                && filled($record->refer_model_id)
            ) {
                $purchaseOrderService->approvePo($record, Auth::id());
            }
        } catch (Throwable $exception) {
            Log::error('CreatePurchaseOrder afterCreate failed', [
                'purchase_order_id' => $record?->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::warning(
                'Pembelian Tersimpan Dengan Catatan',
                $exception,
                'Pembelian berhasil dibuat, tetapi perhitungan total belum berhasil disinkronkan. Silakan muat ulang halaman atau hubungi administrator.'
            );
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('CreatePurchaseOrder handleRecordCreation failed', [
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Membuat Pembelian',
                $exception,
                'Pembelian belum berhasil dibuat. Periksa kembali data yang diisi lalu coba lagi.'
            );

            throw $exception;
        }
    }

    protected function getSubmitFormAction(): Action
    {
        return $this->getCreateFormAction()->icon('heroicon-o-plus-circle');
    }
}
