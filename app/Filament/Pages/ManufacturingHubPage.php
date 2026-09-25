<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BillOfMaterialResource;
use App\Filament\Resources\ManufacturingOrderResource;
use App\Filament\Resources\MaterialIssueResource;
use App\Filament\Resources\ProductionPlanResource;
use App\Filament\Resources\ProductionResource;
use App\Filament\Resources\QualityControlManufactureResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class ManufacturingHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.manufacturing-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Manufaktur';

    protected static ?string $navigationLabel = 'Manufaktur';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'manufacturing-hub';

    protected static function getHubClasses(): array
    {
        return [
            BillOfMaterialResource::class,
            ProductionPlanResource::class,
            ManufacturingOrderResource::class,
            MaterialIssueResource::class,
            ProductionResource::class,
            QualityControlManufactureResource::class,
        ];
    }
}