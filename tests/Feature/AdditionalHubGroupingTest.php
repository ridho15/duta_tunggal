<?php

use App\Filament\Pages\AssetManagementHubPage;
use App\Filament\Pages\ManufacturingHubPage;
use App\Filament\Pages\MasterDataHubPage;
use App\Filament\Pages\UserRolesManagementHubPage;
use App\Filament\Resources\AssetDisposalResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetTransferResource;
use App\Filament\Resources\BillOfMaterialResource;
use App\Filament\Resources\CabangResource;
use App\Filament\Resources\ChartOfAccountResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\DriverResource;
use App\Filament\Resources\ManufacturingOrderResource;
use App\Filament\Resources\MaterialIssueResource;
use App\Filament\Resources\PermissionResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductionPlanResource;
use App\Filament\Resources\ProductionResource;
use App\Filament\Resources\QualityControlManufactureResource;
use App\Filament\Resources\RakResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\TaxSettingResource;
use App\Filament\Resources\UnitOfMeasureResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VehicleResource;
use App\Filament\Resources\WarehouseResource;

function hubStaticProperty(string $class, string $property): mixed
{
    $reflection = new ReflectionClass($class);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);

    return $prop->getValue();
}

test('master data hub page is configured as the visible master data entry', function () {
    expect(hubStaticProperty(MasterDataHubPage::class, 'navigationGroup'))->toBe('Master Data')
        ->and(hubStaticProperty(MasterDataHubPage::class, 'navigationLabel'))->toBe('Pusat Data Master')
        ->and(MasterDataHubPage::getUrl())->toContain('/admin/master-data-hub');
});

test('manufacturing hub page is configured as the visible manufacturing entry', function () {
    expect(hubStaticProperty(ManufacturingHubPage::class, 'navigationGroup'))->toBe('Manufaktur')
        ->and(hubStaticProperty(ManufacturingHubPage::class, 'navigationLabel'))->toBe('Pusat Manufaktur')
        ->and(ManufacturingHubPage::getUrl())->toContain('/admin/manufacturing-hub');
});

test('asset management hub page is hidden and only exposed through the accounting hub', function () {
    expect(AssetManagementHubPage::shouldRegisterNavigation())->toBeFalse()
        ->and(AssetManagementHubPage::getUrl())->toContain('/admin/asset-management-hub');
});

test('user roles management hub page is configured as the visible admin entry', function () {
    expect(hubStaticProperty(UserRolesManagementHubPage::class, 'navigationGroup'))->toBe('Manajemen User dan Role')
        ->and(hubStaticProperty(UserRolesManagementHubPage::class, 'navigationLabel'))->toBe('Pusat Manajemen User & Role')
        ->and(UserRolesManagementHubPage::getUrl())->toContain('/admin/user-roles-management-hub');
});

test('master data sidebar items are hidden and only exposed through the master data hub', function () {
    foreach ([
        ProductResource::class,
        ProductCategoryResource::class,
        SupplierResource::class,
        CustomerResource::class,
        WarehouseResource::class,
        ChartOfAccountResource::class,
        CurrencyResource::class,
        UnitOfMeasureResource::class,
        VehicleResource::class,
        DriverResource::class,
        RakResource::class,
        TaxSettingResource::class,
        CabangResource::class,
    ] as $resourceClass) {
        expect(hubStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('manufacturing sidebar items are hidden and only exposed through the manufacturing hub', function () {
    foreach ([
        BillOfMaterialResource::class,
        ProductionPlanResource::class,
        ManufacturingOrderResource::class,
        MaterialIssueResource::class,
        ProductionResource::class,
        QualityControlManufactureResource::class,
    ] as $resourceClass) {
        expect(hubStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('asset and admin sidebar items are hidden and only exposed through their hubs', function () {
    foreach ([
        AssetResource::class,
        AssetTransferResource::class,
        AssetDisposalResource::class,
        UserResource::class,
        RoleResource::class,
        PermissionResource::class,
    ] as $resourceClass) {
        expect(hubStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});