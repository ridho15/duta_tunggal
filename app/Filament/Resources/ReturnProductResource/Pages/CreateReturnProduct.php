<?php

namespace App\Filament\Resources\ReturnProductResource\Pages;

use App\Filament\Resources\ReturnProductResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateReturnProduct extends CreateRecord
{
    protected static string $resource = ReturnProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        return $data;
    }

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();

        // Validate return number format for new records
        if (!preg_match('/^RN-\d{8}-\d{4}$/', $data['return_number'])) {
            HelperController::sendNotification(
                isSuccess: false,
                title: "Validation Error",
                message: "Format nomor return tidak valid. Gunakan format: RN-YYYYMMDD-XXXX"
            );
            throw ValidationException::withMessages([
                'return_number' => 'Format nomor return tidak valid. Gunakan format: RN-YYYYMMDD-XXXX'
            ]);
        }

        // Additional validation: Ensure warehouse is selected before items
        if (empty($data['warehouse_id'])) {
            HelperController::sendNotification(
                isSuccess: false,
                title: "Validation Error",
                message: "Silakan pilih gudang terlebih dahulu sebelum menambah item retur."
            );
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang wajib dipilih.'
            ]);
        }

        // Additional validation: Ensure from_model is selected before items
        if (empty($data['from_model_type']) || empty($data['from_model_id'])) {
            HelperController::sendNotification(
                isSuccess: false,
                title: "Validation Error",
                message: "Silakan pilih order sumber terlebih dahulu sebelum menambah item retur."
            );
            throw ValidationException::withMessages([
                'from_model_type' => 'Tipe order sumber wajib dipilih.',
                'from_model_id' => 'Order sumber wajib dipilih.'
            ]);
        }

        // Custom validation: Check quantity doesn't exceed max_quantity
        $items = $data['returnProductItem'] ?? [];
        foreach ($items as $index => $item) {
            $quantity = $item['quantity'] ?? 0;
            $maxQuantity = $item['max_quantity'] ?? 0;

            if ($quantity > $maxQuantity && $maxQuantity > 0) {
                HelperController::sendNotification(
                    isSuccess: false,
                    title: "Validation Error",
                    message: "Quantity retur pada item " . ($index + 1) . " tidak boleh melebihi quantity tersedia ({$maxQuantity})."
                );
                throw ValidationException::withMessages([
                    "returnProductItem.{$index}.quantity" => "Quantity retur tidak boleh melebihi quantity tersedia ({$maxQuantity})."
                ]);
            }
        }
    }
}
