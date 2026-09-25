<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AccountPayableResource;
use App\Filament\Resources\CashBankTransactionResource;
use App\Filament\Resources\CashBankTransferResource;
use App\Filament\Resources\DepositResource;
use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\PaymentRequestResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseReceiptResource;
use App\Filament\Resources\PurchaseReturnResource;
use App\Filament\Resources\QualityControlPurchaseResource;
use App\Filament\Resources\VendorPaymentResource;
use App\Traits\HasHubModuleAccess;
use Filament\Pages\Page;

class PurchaseHubPage extends Page
{
    use HasHubModuleAccess;

    protected static string $view = 'filament.pages.purchase-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Pembelian';

    protected static ?string $navigationLabel = 'Pembelian';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'purchase-hub';

    protected static function getHubClasses(): array
    {
        return [
            OrderRequestResource::class,
            PurchaseOrderResource::class,
            QualityControlPurchaseResource::class,
            PurchaseReceiptResource::class,
            PurchaseReturnResource::class,
            AccountPayableResource::class,
            PurchaseInvoiceResource::class,
            PaymentRequestResource::class,
            VendorPaymentResource::class,
            CashBankTransactionResource::class,
            DepositResource::class,
            CashBankTransferResource::class,
        ];
    }
}