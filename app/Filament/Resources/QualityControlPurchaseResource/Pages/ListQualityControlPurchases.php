<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQualityControlPurchases extends ListRecords
{
    protected static string $resource = QualityControlPurchaseResource::class;

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
            // QualityControlPurchaseResource\Widgets\PurchaseReturnAutomationStatsWidget::class,
        ];
    }
}