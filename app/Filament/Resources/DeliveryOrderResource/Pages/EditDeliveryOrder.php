<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Services\DeliveryOrderItemService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryOrder extends EditRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Additional validation before updating
        app(DeliveryOrderItemService::class)->validateItemsForSalesOrder(
            (int) ($data['sales_order_id'] ?? 0),
            $data['deliveryOrderItem'] ?? [],
            $this->record->id
        );

        return $data;
    }
}
