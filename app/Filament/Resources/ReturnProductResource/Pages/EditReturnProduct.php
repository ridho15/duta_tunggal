<?php

namespace App\Filament\Resources\ReturnProductResource\Pages;

use App\Filament\Resources\ReturnProductResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditReturnProduct extends EditRecord
{
    protected static string $resource = ReturnProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('returnProductItem.fromItemModel');

        if (isset($data['returnProductItem']) && is_array($data['returnProductItem'])) {
            foreach ($data['returnProductItem'] as &$itemData) {
                $returnProductItem = $this->record->returnProductItem->firstWhere('id', $itemData['id'] ?? null);
                $fromItemModel = $returnProductItem?->fromItemModel;
                if ($fromItemModel) {
                    $itemData['max_quantity'] = (float) $fromItemModel->quantity;
                }
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getState();

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
            $quantity = (float) ($item['quantity'] ?? 0);
            $maxQuantity = (float) ($item['max_quantity'] ?? 0);

            // Fallback: resolve from source item model if state was zero
            if ($maxQuantity <= 0 && ! empty($item['from_item_model_type']) && ! empty($item['from_item_model_id'])) {
                $modelClass = $item['from_item_model_type'];
                if (class_exists($modelClass)) {
                    $sourceItem = $modelClass::find($item['from_item_model_id']);
                    if ($sourceItem) {
                        $maxQuantity = (float) $sourceItem->quantity;
                    }
                }
            }

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
