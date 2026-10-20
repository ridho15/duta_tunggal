<?php

use App\Filament\Pages\AccountingHubPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DashboardHubPage;
use App\Filament\Pages\DeliveryHubPage;
use App\Filament\Pages\FinancePurchaseHubPage;
use App\Filament\Pages\FinanceReportHubPage;
use App\Filament\Pages\FinanceSalesHubPage;
use App\Filament\Pages\FinancialStatementPage;
use App\Filament\Pages\InventoryHubPage;
use App\Filament\Pages\InventoryReportPage;
use App\Filament\Pages\ManufacturingHubPage;
use App\Filament\Pages\MasterDataHubPage;
use App\Filament\Pages\MyDashboard;
use App\Filament\Pages\PaymentHubPage;
use App\Filament\Pages\PurchaseHubPage;
use App\Filament\Pages\PurchaseReportPage;
use App\Filament\Pages\SalesHubPage;
use App\Filament\Pages\SalesReportPage;
use App\Filament\Pages\UserRolesManagementHubPage;
use App\Filament\Pages\WarehouseHubPage;
use App\Filament\Pages\AssetManagementHubPage;
use App\Filament\Resources\AccountReceivableResource;
use App\Filament\Resources\AccountingPeriodResource;
use App\Filament\Resources\ApprovalRuleResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\BankReconciliationResource;
use App\Filament\Resources\BillOfMaterialResource;
use App\Filament\Resources\CabangResource;
use App\Filament\Resources\CashBankTransactionResource;
use App\Filament\Resources\ChartOfAccountResource;
use App\Filament\Resources\CustomerReceiptResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerReturnResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\DeliveryScheduleResource;
use App\Filament\Resources\InventoryStockResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\ManufacturingOrderResource;
use App\Filament\Resources\MaterialIssueResource;
use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\OtherSaleResource;
use App\Filament\Resources\PaymentRequestResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductionPlanResource;
use App\Filament\Resources\ProductionResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseReceiptResource;
use App\Filament\Resources\PurchaseReturnResource;
use App\Filament\Resources\QualityControlManufactureResource;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\ReturnProductResource;
use App\Filament\Resources\SaleOrderResource;
use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Resources\StockAdjustmentResource;
use App\Filament\Resources\StockOpnameResource;
use App\Filament\Resources\StockTransferResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SuratJalanResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VehicleResource;
use App\Filament\Resources\VendorPaymentResource;
use App\Filament\Resources\WarehouseConfirmationResource;
use App\Filament\Resources\WarehouseResource;


if (! function_exists('sidebarStaticProperty')) {
    function sidebarStaticProperty(string $class, string $property): mixed
    {
        $reflection = new ReflectionClass($class);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue();
    }
}

test('all 15 legacy hub pages are hidden from navigation', function () {
    $hubPages = [
        DashboardHubPage::class,
        SalesHubPage::class,
        PurchaseHubPage::class,
        DeliveryHubPage::class,
        AccountingHubPage::class,
        InventoryHubPage::class,
        MasterDataHubPage::class,
        UserRolesManagementHubPage::class,
        ManufacturingHubPage::class,
        FinanceSalesHubPage::class,
        FinancePurchaseHubPage::class,
        PaymentHubPage::class,
        FinanceReportHubPage::class,
        AssetManagementHubPage::class,
        WarehouseHubPage::class,
    ];

    foreach ($hubPages as $hubPage) {
        expect($hubPage::shouldRegisterNavigation())
            ->toBeFalse("Expected {$hubPage} to be hidden from navigation");
    }
});

test('group 1 BERANDA has exactly 1 menu: Tugas Saya', function () {
    expect(Dashboard::shouldRegisterNavigation())->toBeTrue()
        ->and(sidebarStaticProperty(Dashboard::class, 'navigationGroup'))->toBe('BERANDA')
        ->and(sidebarStaticProperty(Dashboard::class, 'navigationLabel'))->toBe('Tugas Saya')
        ->and(sidebarStaticProperty(Dashboard::class, 'navigationSort'))->toBe(1)
        ->and(sidebarStaticProperty(Dashboard::class, 'navigationIcon'))->toBe('heroicon-o-home');
});

test('group 2 PENJUALAN has 8 active menus with proper labels and sort', function () {
    $expected = [
        QuotationResource::class => ['Penawaran Harga', 1, 'heroicon-o-tag'],
        SaleOrderResource::class => ['Pesanan Penjualan (SO)', 2, 'heroicon-o-shopping-bag'],
        DeliveryOrderResource::class => ['Perintah Kirim (DO)', 3, 'heroicon-o-clipboard-document-check'],
        SuratJalanResource::class => ['Surat Jalan', 4, 'heroicon-o-truck'],
        SalesInvoiceResource::class => ['Invoice Penjualan', 5, 'heroicon-o-document-text'],
        CustomerReceiptResource::class => ['Terima Pembayaran', 6, 'heroicon-o-banknotes'],
        CustomerReturnResource::class => ['Retur Penjualan', 7, 'heroicon-o-arrow-uturn-left'],
        ReturnProductResource::class => ['Retur DO', 8, 'heroicon-o-arrow-path'],
    ];

    foreach ($expected as $class => [$label, $sort, $icon]) {
        expect(sidebarStaticProperty($class, 'shouldRegisterNavigation'))->toBeTrue()
            ->and(sidebarStaticProperty($class, 'navigationGroup'))->toBe('PENJUALAN')
            ->and(sidebarStaticProperty($class, 'navigationLabel'))->toBe($label)
            ->and(sidebarStaticProperty($class, 'navigationSort'))->toBe($sort)
            ->and(sidebarStaticProperty($class, 'navigationIcon'))->toBe($icon);
    }
});

test('group 3 GUDANG has 6 active menus with proper labels and sort', function () {
    $expected = [
        WarehouseConfirmationResource::class => ['Siapkan & Kirim', 1, 'heroicon-o-clipboard-document-list'],
        PurchaseReceiptResource::class => ['Terima Barang', 2, 'heroicon-o-inbox-arrow-down'],
        InventoryStockResource::class => ['Cek Stok', 3, 'heroicon-o-circle-stack'],
        StockTransferResource::class => ['Pindah Stok', 4, 'heroicon-o-arrows-right-left'],
        StockOpnameResource::class => ['Stock Opname', 5, 'heroicon-o-document-magnifying-glass'],
        StockAdjustmentResource::class => ['Penyesuaian Stok', 6, 'heroicon-o-adjustments-horizontal'],
    ];

    foreach ($expected as $class => [$label, $sort, $icon]) {
        expect(sidebarStaticProperty($class, 'shouldRegisterNavigation'))->toBeTrue()
            ->and(sidebarStaticProperty($class, 'navigationGroup'))->toBe('GUDANG')
            ->and(sidebarStaticProperty($class, 'navigationLabel'))->toBe($label)
            ->and(sidebarStaticProperty($class, 'navigationSort'))->toBe($sort)
            ->and(sidebarStaticProperty($class, 'navigationIcon'))->toBe($icon);
    }
});

test('group 4 PEMBELIAN has 5 active menus with proper labels and sort', function () {
    $expected = [
        OrderRequestResource::class => ['Permintaan Pembelian', 1, 'heroicon-o-document-plus'],
        PurchaseOrderResource::class => ['Pesanan Pembelian (PO)', 2, 'heroicon-o-shopping-cart'],
        PurchaseInvoiceResource::class => ['Invoice Pembelian', 3, 'heroicon-o-receipt-percent'],
        VendorPaymentResource::class => ['Bayar Supplier', 4, 'heroicon-o-credit-card'],
        PurchaseReturnResource::class => ['Retur Pembelian', 5, 'heroicon-o-arrow-uturn-left'],
    ];

    foreach ($expected as $class => [$label, $sort, $icon]) {
        expect(sidebarStaticProperty($class, 'shouldRegisterNavigation'))->toBeTrue()
            ->and(sidebarStaticProperty($class, 'navigationGroup'))->toBe('PEMBELIAN')
            ->and(sidebarStaticProperty($class, 'navigationLabel'))->toBe($label)
            ->and(sidebarStaticProperty($class, 'navigationSort'))->toBe($sort)
            ->and(sidebarStaticProperty($class, 'navigationIcon'))->toBe($icon);
    }
});

test('group 5 KEUANGAN has 7 active menus with proper labels and sort', function () {
    $expected = [
        CashBankTransactionResource::class => ['Kas & Bank', 1, 'heroicon-o-banknotes'],
        AccountReceivableResource::class => ['Piutang & Hutang', 2, 'heroicon-o-scale'],
        PaymentRequestResource::class => ['Pengajuan Dana', 3, 'heroicon-o-document-currency-dollar'],
        JournalEntryResource::class => ['Jurnal', 4, 'heroicon-o-book-open'],
        BankReconciliationResource::class => ['Rekonsiliasi Bank', 5, 'heroicon-o-arrow-path-rounded-square'],
        AssetResource::class => ['Aset Tetap', 6, 'heroicon-o-building-office-2'],
        AccountingPeriodResource::class => ['Tutup Buku', 7, 'heroicon-o-lock-closed'],
    ];

    foreach ($expected as $class => [$label, $sort, $icon]) {
        expect(sidebarStaticProperty($class, 'shouldRegisterNavigation'))->toBeTrue()
            ->and(sidebarStaticProperty($class, 'navigationGroup'))->toBe('KEUANGAN')
            ->and(sidebarStaticProperty($class, 'navigationLabel'))->toBe($label)
            ->and(sidebarStaticProperty($class, 'navigationSort'))->toBe($sort)
            ->and(sidebarStaticProperty($class, 'navigationIcon'))->toBe($icon);
    }
});

test('group 6 LAPORAN has 5 active menus with proper labels and sort', function () {
    $expected = [
        MyDashboard::class => ['Dashboard Owner', 1, 'heroicon-o-chart-pie'],
        SalesReportPage::class => ['Penjualan', 2, 'heroicon-o-chart-bar'],
        PurchaseReportPage::class => ['Pembelian', 3, 'heroicon-o-arrow-trending-up'],
        InventoryReportPage::class => ['Stok', 4, 'heroicon-o-cube-transparent'],
        FinancialStatementPage::class => ['Laporan Keuangan', 5, 'heroicon-o-document-chart-bar'],
    ];

    foreach ($expected as $class => [$label, $sort, $icon]) {
        expect($class::shouldRegisterNavigation())->toBeTrue()
            ->and(sidebarStaticProperty($class, 'navigationGroup'))->toBe('LAPORAN')
            ->and(sidebarStaticProperty($class, 'navigationLabel'))->toBe($label)
            ->and(sidebarStaticProperty($class, 'navigationSort'))->toBe($sort)
            ->and(sidebarStaticProperty($class, 'navigationIcon'))->toBe($icon);
    }
});

test('group 7 DATA MASTER has 7 active menus with proper labels and sort', function () {
    $expected = [
        ProductResource::class => ['Produk', 1, 'heroicon-o-cube'],
        CustomerResource::class => ['Customer', 2, 'heroicon-o-user-group'],
        SupplierResource::class => ['Supplier', 3, 'heroicon-o-building-storefront'],
        WarehouseResource::class => ['Gudang & Rak', 4, 'heroicon-o-building-office'],
        CabangResource::class => ['Cabang', 5, 'heroicon-o-map-pin'],
        VehicleResource::class => ['Kendaraan & Driver', 6, 'heroicon-o-truck'],
        ChartOfAccountResource::class => ['Akun & Pajak', 7, 'heroicon-o-calculator'],
    ];

    foreach ($expected as $class => [$label, $sort, $icon]) {
        expect(sidebarStaticProperty($class, 'shouldRegisterNavigation'))->toBeTrue()
            ->and(sidebarStaticProperty($class, 'navigationGroup'))->toBe('DATA MASTER')
            ->and(sidebarStaticProperty($class, 'navigationLabel'))->toBe($label)
            ->and(sidebarStaticProperty($class, 'navigationSort'))->toBe($sort)
            ->and(sidebarStaticProperty($class, 'navigationIcon'))->toBe($icon);
    }
});

test('group 8 PENGATURAN has 2 active menus with proper labels and sort', function () {
    $expected = [
        UserResource::class => ['Pengguna & Hak Akses', 1, 'heroicon-o-shield-check'],
        ApprovalRuleResource::class => ['Aturan Persetujuan', 2, 'heroicon-o-adjustments-vertical'],
    ];

    foreach ($expected as $class => [$label, $sort, $icon]) {
        expect(sidebarStaticProperty($class, 'shouldRegisterNavigation'))->toBeTrue()
            ->and(sidebarStaticProperty($class, 'navigationGroup'))->toBe('PENGATURAN')
            ->and(sidebarStaticProperty($class, 'navigationLabel'))->toBe($label)
            ->and(sidebarStaticProperty($class, 'navigationSort'))->toBe($sort)
            ->and(sidebarStaticProperty($class, 'navigationIcon'))->toBe($icon);
    }
});

test('manufacturing and secondary modules are hidden from navigation', function () {
    $hiddenResources = [
        BillOfMaterialResource::class,
        ProductionPlanResource::class,
        ManufacturingOrderResource::class,
        MaterialIssueResource::class,
        ProductionResource::class,
        QualityControlManufactureResource::class,
        DeliveryScheduleResource::class,
        OtherSaleResource::class,
    ];

    foreach ($hiddenResources as $resourceClass) {
        expect(sidebarStaticProperty($resourceClass, 'shouldRegisterNavigation'))
            ->toBeFalse("Expected {$resourceClass} to be hidden from navigation");
    }
});