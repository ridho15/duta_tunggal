<?php

use App\Filament\Pages\AccountingHubPage;
use App\Filament\Pages\ArApManagementPage;
use App\Filament\Pages\DeliveryHubPage;
use App\Filament\Pages\FinancePurchaseHubPage;
use App\Filament\Pages\FinanceSalesHubPage;
use App\Filament\Pages\MyDashboard;
use App\Filament\Pages\PaymentHubPage;
use App\Filament\Pages\PurchaseHubPage;
use App\Filament\Pages\WarehouseHubPage;
use App\Filament\Resources\AccountPayableResource;
use App\Filament\Resources\AccountReceivableResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\DeliveryScheduleResource;
use App\Filament\Resources\AgeingScheduleResource;
use App\Filament\Resources\BankReconciliationResource;
use App\Filament\Resources\BillOfMaterialResource;
use App\Filament\Resources\CashBankTransactionResource;
use App\Filament\Resources\CashBankTransferResource;
use App\Filament\Resources\CustomerReturnResource;
use App\Filament\Resources\CustomerReceiptResource;
use App\Filament\Resources\DepositResource;
use App\Filament\Resources\InventoryStockResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\ManufacturingOrderResource;
use App\Filament\Resources\MaterialIssueResource;
use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\OtherSaleResource;
use App\Filament\Resources\PaymentRequestResource;
use App\Filament\Resources\ProductionPlanResource;
use App\Filament\Resources\ProductionResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseReceiptResource;
use App\Filament\Resources\PurchaseReturnResource;
use App\Filament\Resources\QualityControlManufactureResource;
use App\Filament\Resources\QualityControlPurchaseResource;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries;
use App\Filament\Resources\ReturnProductResource;
use App\Filament\Resources\SaleOrderResource;
use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Resources\StockAdjustmentResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\StockOpnameResource;
use App\Filament\Resources\StockTransferResource;
use App\Filament\Resources\SuratJalanResource;
use App\Filament\Resources\VendorPaymentResource;
use App\Filament\Resources\VoucherRequestResource;
use App\Filament\Resources\WarehouseConfirmationResource;

function sidebarStaticProperty(string $class, string $property): mixed
{
    $reflection = new ReflectionClass($class);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);

    return $prop->getValue();
}

test('finance dashboard is no longer grouped under finance reports', function () {
    expect(sidebarStaticProperty(MyDashboard::class, 'navigationGroup'))->toBe('Finance')
    ->and(sidebarStaticProperty(MyDashboard::class, 'navigationLabel'))->toBe('Dashboard Finance');
});

test('accounting hub page is configured as the visible accounting menu entry', function () {
    expect(sidebarStaticProperty(AccountingHubPage::class, 'navigationGroup'))->toBe('Akuntansi Keuangan')
        ->and(sidebarStaticProperty(AccountingHubPage::class, 'navigationLabel'))->toBe('Pusat Akuntansi')
        ->and(AccountingHubPage::getUrl())->toContain('/admin/accounting-hub');
});

test('warehouse hub page is configured as the visible warehouse menu entry', function () {
    expect(sidebarStaticProperty(WarehouseHubPage::class, 'navigationGroup'))->toBe('Gudang')
        ->and(sidebarStaticProperty(WarehouseHubPage::class, 'navigationLabel'))->toBe('Pusat Gudang')
        ->and(WarehouseHubPage::getUrl())->toContain('/admin/warehouse-hub');
});

test('purchase hub page is configured as the visible purchase menu entry', function () {
    expect(sidebarStaticProperty(PurchaseHubPage::class, 'navigationGroup'))->toBe('Pembelian')
        ->and(sidebarStaticProperty(PurchaseHubPage::class, 'navigationLabel'))->toBe('Pusat Pembelian')
        ->and(PurchaseHubPage::getUrl())->toContain('/admin/purchase-hub');
});

test('delivery hub page is configured as the visible delivery menu entry', function () {
    expect(sidebarStaticProperty(DeliveryHubPage::class, 'navigationGroup'))->toBe('Pengiriman')
        ->and(sidebarStaticProperty(DeliveryHubPage::class, 'navigationLabel'))->toBe('Pusat Pengiriman')
        ->and(DeliveryHubPage::getUrl())->toContain('/admin/delivery-hub');
});

test('finance sales, purchase, and payment hubs are configured as the visible transaction menu entries', function () {
    expect(sidebarStaticProperty(FinanceSalesHubPage::class, 'navigationGroup'))->toBe('Keuangan Penjualan')
        ->and(sidebarStaticProperty(FinanceSalesHubPage::class, 'navigationLabel'))->toBe('Pusat Keuangan Penjualan')
        ->and(FinanceSalesHubPage::getUrl())->toContain('/admin/finance-sales-hub')
        ->and(sidebarStaticProperty(FinancePurchaseHubPage::class, 'navigationGroup'))->toBe('Keuangan Pembelian')
        ->and(sidebarStaticProperty(FinancePurchaseHubPage::class, 'navigationLabel'))->toBe('Pusat Keuangan Pembelian')
        ->and(FinancePurchaseHubPage::getUrl())->toContain('/admin/finance-purchase-hub')
        ->and(sidebarStaticProperty(PaymentHubPage::class, 'navigationGroup'))->toBe('Pembayaran Keuangan')
        ->and(sidebarStaticProperty(PaymentHubPage::class, 'navigationLabel'))->toBe('Pusat Pembayaran')
        ->and(PaymentHubPage::getUrl())->toContain('/admin/payment-hub');
});

test('detailed accounting sidebar items are hidden and only exposed through the accounting hub', function () {
    expect(ArApManagementPage::shouldRegisterNavigation())->toBeFalse();

    foreach ([
        JournalEntryResource::class,
        GroupedJournalEntries::class,
        VoucherRequestResource::class,
        BankReconciliationResource::class,
        AgeingScheduleResource::class,
    ] as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('detailed warehouse sidebar items are hidden and only exposed through the warehouse hub', function () {
    foreach ([
        StockTransferResource::class,
        StockAdjustmentResource::class,
        StockOpnameResource::class,
        InventoryStockResource::class,
        StockMovementResource::class,
        ReturnProductResource::class,
        WarehouseConfirmationResource::class,
    ] as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('detailed purchase sidebar items are hidden and only exposed through the purchase hub', function () {
    foreach ([
        OrderRequestResource::class,
        PurchaseOrderResource::class,
        QualityControlPurchaseResource::class,
        PurchaseReceiptResource::class,
        PurchaseReturnResource::class,
    ] as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('detailed delivery sidebar items are hidden and only exposed through the delivery hub', function () {
    foreach ([
        DeliveryOrderResource::class,
        DeliveryScheduleResource::class,
        SuratJalanResource::class,
    ] as $resourceClass) {
        if ($resourceClass === SuratJalanResource::class) {
            expect(SuratJalanResource::shouldRegisterNavigation())->toBeFalse();
            continue;
        }

        expect(sidebarStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('detailed finance sales and purchase sidebar items are hidden and only exposed through their hubs', function () {
    foreach ([
        AccountReceivableResource::class,
        SalesInvoiceResource::class,
        OtherSaleResource::class,
        AccountPayableResource::class,
        PurchaseInvoiceResource::class,
    ] as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('detailed payment sidebar items are hidden and only exposed through the payment hub', function () {
    foreach ([
        PaymentRequestResource::class,
        CustomerReceiptResource::class,
        VendorPaymentResource::class,
        CashBankTransactionResource::class,
        DepositResource::class,
    ] as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }

    expect(CashBankTransferResource::shouldRegisterNavigation())->toBeFalse();
});

test('sales and manufacturing groups use consistent Indonesian labels', function () {
    foreach ([SaleOrderResource::class, QuotationResource::class] as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'navigationGroup'))->toBe('Penjualan');
    }

    foreach ([
        BillOfMaterialResource::class,
        ManufacturingOrderResource::class,
        MaterialIssueResource::class,
        ProductionPlanResource::class,
        ProductionResource::class,
        QualityControlManufactureResource::class,
    ] as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'navigationGroup'))->toBe('Manufaktur');
    }
});

test('child menu labels use Indonesian wording in the refactored sections', function () {
    expect(sidebarStaticProperty(SaleOrderResource::class, 'navigationLabel'))->toBe('Pesanan Penjualan')
        ->and(sidebarStaticProperty(OrderRequestResource::class, 'navigationLabel'))->toBe('Permintaan Pembelian')
        ->and(sidebarStaticProperty(PurchaseOrderResource::class, 'navigationLabel'))->toBe('Pesanan Pembelian')
        ->and(sidebarStaticProperty(PurchaseReceiptResource::class, 'navigationLabel'))->toBe('Penerimaan Pembelian')
        ->and(sidebarStaticProperty(PurchaseReturnResource::class, 'navigationLabel'))->toBe('Retur Pembelian')
        ->and(sidebarStaticProperty(PaymentRequestResource::class, 'navigationLabel'))->toBe('Permintaan Pembayaran')
        ->and(sidebarStaticProperty(CustomerReceiptResource::class, 'navigationLabel'))->toBe('Penerimaan Pelanggan')
        ->and(sidebarStaticProperty(VendorPaymentResource::class, 'navigationLabel'))->toBe('Pembayaran Vendor')
        ->and(sidebarStaticProperty(QualityControlPurchaseResource::class, 'navigationLabel'))->toBe('Kontrol Kualitas Pembelian')
        ->and(sidebarStaticProperty(QualityControlManufactureResource::class, 'navigationLabel'))->toBe('Kontrol Kualitas Produksi')
        ->and(sidebarStaticProperty(ManufacturingOrderResource::class, 'navigationLabel'))->toBe('Perintah Produksi')
        ->and(sidebarStaticProperty(BillOfMaterialResource::class, 'navigationLabel'))->toBe('Daftar Material')
        ->and(sidebarStaticProperty(DeliveryOrderResource::class, 'navigationLabel'))->toBe('Perintah Pengiriman')
        ->and(sidebarStaticProperty(CustomerReturnResource::class, 'navigationGroup'))->toBe('Retur Pelanggan');
});