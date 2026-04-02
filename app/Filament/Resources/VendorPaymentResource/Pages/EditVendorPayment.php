<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditVendorPayment extends EditRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('EditVendorPayment handleRecordUpdate failed', [
                'vendor_payment_id' => $record->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Memperbarui Pembayaran Vendor',
                $exception,
                'Perubahan pembayaran vendor belum berhasil disimpan. Periksa kembali data pembayaran lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
