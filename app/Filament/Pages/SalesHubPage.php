<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AccountReceivableResource;
use App\Filament\Resources\CustomerReceiptResource;
use App\Filament\Resources\CustomerReturnResource;
use App\Filament\Resources\OtherSaleResource;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\SaleOrderResource;
use App\Filament\Resources\SalesInvoiceResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class SalesHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.sales-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Penjualan';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'sales-hub';

    protected static function getHubClasses(): array
    {
        return [
            QuotationResource::class,
            SaleOrderResource::class,
            CustomerReturnResource::class,
            AccountReceivableResource::class,
            SalesInvoiceResource::class,
            CustomerReceiptResource::class,
            OtherSaleResource::class,
        ];
    }
}