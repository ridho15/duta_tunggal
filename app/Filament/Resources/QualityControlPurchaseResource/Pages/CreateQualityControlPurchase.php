<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateQualityControlPurchase extends CreateRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('CreateQualityControlPurchase handleRecordCreation failed', [
                'from_model_id' => $data['from_model_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Membuat QC Pembelian',
                $exception,
                'QC pembelian belum berhasil dibuat. Periksa kembali quantity, gudang, dan item purchase order yang dipilih lalu coba lagi.'
            );

            throw $exception;
        }
    }
}