<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Services\SalesOrderService;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSaleOrder extends EditRecord
{
    protected static string $resource = SaleOrderResource::class;

    // protected static string $view = 'filament.components.sale-order.form';

    protected static ?string $title = 'Ubah Penjualan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave()
    {
        $salesOrderService = new SalesOrderService;
        $salesOrderService->updateTotalAmount($this->getRecord());
    }
}
