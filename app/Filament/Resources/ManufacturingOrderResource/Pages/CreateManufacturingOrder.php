<?php

namespace App\Filament\Resources\ManufacturingOrderResource\Pages;

use App\Filament\Resources\ManufacturingOrderResource;
use App\Services\ManufacturingService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateManufacturingOrder extends CreateRecord
{
    protected static string $resource = ManufacturingOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        return $data;
    }

    protected function afterCreate()
    {
        $manufacturingService = new ManufacturingService;
        $manufacturingService->createWarehouseConfirmation($this->getRecord());
    }
}
