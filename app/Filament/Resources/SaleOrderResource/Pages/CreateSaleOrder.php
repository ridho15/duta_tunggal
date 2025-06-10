<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Http\Controllers\HelperController;
use App\Services\SalesOrderService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSaleOrder extends CreateRecord
{
    protected static string $resource = SaleOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate()
    {
        $salesOrderService = new SalesOrderService;
        $salesOrderService->updateTotalAmount($this->getRecord());
    }
}
