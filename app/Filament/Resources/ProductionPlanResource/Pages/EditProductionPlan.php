<?php

namespace App\Filament\Resources\ProductionPlanResource\Pages;

use App\Filament\Resources\ProductionPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductionPlan extends EditRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
