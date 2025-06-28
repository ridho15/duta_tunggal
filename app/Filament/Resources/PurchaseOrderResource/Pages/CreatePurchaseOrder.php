<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Services\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static ?string $title = 'Buat Pembelian';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;
        return $data;
    }

    protected function afterCreate()
    {
        $purchaseOrderService = app(PurchaseOrderService::class);
        $purchaseOrderService->updateTotalAmount($this->getRecord());
    }

    protected function getSubmitFormAction(): Action
    {
        return $this->getCreateFormAction()->icon('heroicon-o-plus-circle');
    }
}
