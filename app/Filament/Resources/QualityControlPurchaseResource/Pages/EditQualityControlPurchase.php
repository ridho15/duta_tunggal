<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditQualityControlPurchase extends EditRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['product_name'] = $this->record->product->name ?? '';
        $data['sku'] = $this->record->product->sku ?? '';
        $data['quantity_received'] = $this->record->fromModel->qty_accepted ?? 0;
        $data['uom'] = $this->record->product->uom->name ?? '';
        return $data;
    }
}