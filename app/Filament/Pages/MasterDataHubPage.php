<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CabangResource;
use App\Filament\Resources\ChartOfAccountResource;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\DriverResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\RakResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\TaxSettingResource;
use App\Filament\Resources\UnitOfMeasureResource;
use App\Filament\Resources\VehicleResource;
use App\Filament\Resources\WarehouseResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class MasterDataHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.master-data-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Data Master';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'master-data-hub';

    protected static function getHubClasses(): array
    {
        return [
            ProductResource::class,
            ProductCategoryResource::class,
            UnitOfMeasureResource::class,
            RakResource::class,
            WarehouseResource::class,
            CabangResource::class,
            CustomerResource::class,
            SupplierResource::class,
            ChartOfAccountResource::class,
            CurrencyResource::class,
            TaxSettingResource::class,
            VehicleResource::class,
            DriverResource::class,
        ];
    }
}