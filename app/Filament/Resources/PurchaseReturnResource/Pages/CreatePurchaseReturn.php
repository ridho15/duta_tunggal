<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Services\PurchaseReturnService;
use App\Support\ProcurementFailureNotifier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseReturnService::class)->create($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('CreatePurchaseReturn handleRecordCreation failed', [
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Membuat Retur Pembelian',
                $exception,
                'Retur pembelian belum berhasil dibuat. Periksa kembali data retur lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
