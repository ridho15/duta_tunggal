<?php

namespace App\Filament\Resources\DeliveryOrderApprovalLogResource\Pages;

use App\Filament\Resources\DeliveryOrderApprovalLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryOrderApprovalLogs extends ManageRecords
{
    protected static string $resource = DeliveryOrderApprovalLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
