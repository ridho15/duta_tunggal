<?php

namespace App\Filament\Resources\QualityControlManufactureResource\Pages;

use App\Filament\Resources\QualityControlManufactureResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQualityControlManufactures extends ListRecords
{
    protected static string $resource = QualityControlManufactureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // QualityControlManufactureResource\Widgets\ManufactureQcStatsWidget::class,
        ];
    }
}