<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateQualityControlPurchase extends CreateRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

    protected function afterFill(): void
    {
        $purchaseOrderItem = QualityControlPurchaseResource::defaultPurchaseOrderItemForQuery();

        if (! $purchaseOrderItem) {
            return;
        }

        $state = $this->form->getRawState();
        $state = $state instanceof Arrayable ? $state->toArray() : $state;

        $this->form->fill(array_merge(
            $state,
            QualityControlPurchaseResource::formStateForPurchaseOrderItem($purchaseOrderItem),
        ));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!QualityControlPurchaseResource::canChooseInspector()) {
            $data['inspected_by'] = Auth::id();
        }

        return QualityControlPurchaseResource::validateQcPurchaseCreateQuantities($data);
    }

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

            if ($this->isQuantityException($exception)) {
                throw ValidationException::withMessages([
                    'quantity_received' => $exception->getMessage(),
                    'passed_quantity' => $exception->getMessage(),
                ]);
            }

            ProcurementFailureNotifier::danger(
                'Gagal Membuat QC Pembelian',
                $exception,
                'QC pembelian belum berhasil dibuat. Periksa kembali quantity, gudang, dan item purchase order yang dipilih lalu coba lagi.'
            );

            throw $exception;
        }
    }

    private function isQuantityException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'quantity')
            || str_contains($message, 'qty received')
            || str_contains($message, 'available quantity');
    }
}
