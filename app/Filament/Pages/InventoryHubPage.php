<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryStockResource;
use App\Filament\Resources\Reports\InventoryCardResource;
use App\Filament\Resources\ReturnProductResource;
use App\Filament\Resources\StockAdjustmentResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\StockOpnameResource;
use App\Filament\Resources\StockTransferResource;
use App\Filament\Resources\WarehouseConfirmationResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class InventoryHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.inventory-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'inventory-hub';

    protected static function getHubClasses(): array
    {
        return [
            StockTransferResource::class,
            StockAdjustmentResource::class,
            StockOpnameResource::class,
            ReturnProductResource::class,
            InventoryStockResource::class,
            StockMovementResource::class,
            WarehouseConfirmationResource::class,
            InventoryCardResource::class,
        ];
    }
}