<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\DeliveryScheduleResource;
use App\Filament\Resources\SuratJalanResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class DeliveryHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.delivery-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Pengiriman';

    protected static ?string $navigationLabel = 'Pengiriman';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'delivery-hub';

    protected static function getHubClasses(): array
    {
        return [
            DeliveryOrderResource::class,
            SuratJalanResource::class,
            DeliveryScheduleResource::class,
        ];
    }
}