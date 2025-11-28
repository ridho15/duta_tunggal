<?php

namespace App\Filament\Resources\ProductionPlanResource\Pages;

use App\Filament\Resources\ProductionPlanResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionPlans extends ListRecords
{
    protected static string $resource = ProductionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus')->label('Buat Rencana Produksi'),
        ];
    }
}
