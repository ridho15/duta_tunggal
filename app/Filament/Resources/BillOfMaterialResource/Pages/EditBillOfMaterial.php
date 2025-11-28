<?php

namespace App\Filament\Resources\BillOfMaterialResource\Pages;

use App\Filament\Resources\BillOfMaterialResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBillOfMaterial extends EditRecord
{
    protected static string $resource = BillOfMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->getRecord()->product;
        $listConversions = [];
        foreach ($product->unitConversions as $index => $conversion) {
            $listConversions[$index] = [
                'uom_id' => $conversion->uom_id,
                'nilai_konversi' => $conversion->nilai_konversi
            ];
        }

        $data['satuan_konversi'] = $listConversions;

        // Convert string values to numeric for proper calculations
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                if (isset($item['unit_price'])) {
                    $data['items'][$key]['unit_price'] = (float) $item['unit_price'];
                }
                if (isset($item['quantity'])) {
                    $data['items'][$key]['quantity'] = (float) $item['quantity'];
                }
                if (isset($item['subtotal'])) {
                    $data['items'][$key]['subtotal'] = (float) $item['subtotal'];
                }
            }
        }

        // Convert labor_cost and overhead_cost from indonesianMoney formatted strings to float
        if (isset($data['labor_cost'])) {
            $data['labor_cost'] = \App\Http\Controllers\HelperController::parseIndonesianMoney($data['labor_cost']);
        }
        if (isset($data['overhead_cost'])) {
            $data['overhead_cost'] = \App\Http\Controllers\HelperController::parseIndonesianMoney($data['overhead_cost']);
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert labor_cost and overhead_cost from indonesianMoney formatted strings to float
        if (isset($data['labor_cost'])) {
            $data['labor_cost'] = \App\Http\Controllers\HelperController::parseIndonesianMoney($data['labor_cost']);
        }
        if (isset($data['overhead_cost'])) {
            $data['overhead_cost'] = \App\Http\Controllers\HelperController::parseIndonesianMoney($data['overhead_cost']);
        }
        return $data;
    }
    
    protected function afterSave(): void
    {
        // Sync invoice items
        $total_cost = 0;
        foreach($this->record->items as $item){
            $total_cost += $item->quantity * $item->unit_price;
        }
        $total_cost += $this->record->labor_cost + $this->record->overhead_cost;
        $this->record->total_cost = $total_cost;
        $this->record->save();
    }
}
