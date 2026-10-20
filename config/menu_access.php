<?php

use App\Filament\Pages;
use App\Filament\Resources;

/*
|--------------------------------------------------------------------------
| Hak akses menu per peran (Spesifikasi UI/UX v1.1 Bagian 5 + menu_mapping.json)
|--------------------------------------------------------------------------
|
| `role_groups` memetakan kode peran di spesifikasi ke nama role Spatie yang
| ada di database. Satu user boleh punya beberapa role; aksesnya digabung.
|
| `menus` memetakan class Resource/Page ke kode peran yang boleh MELIHAT menu
| dan MEMBUKA URL-nya. Class yang tidak tercantum di sini tidak dibatasi oleh
| file ini (tetap dibatasi Policy/permission yang sudah ada).
|
| Pembatasan ini MENAMBAH, bukan mengganti, pengecekan Policy: user tetap harus
| punya permission "view any ..." agar halaman bisa dibuka.
|
| `modules` mengatur menu yang hanya tampil bila modul diaktifkan di
| PENGATURAN → Modul Aktif.
*/

return [

    'role_groups' => [
        // Owner / Super Admin / Admin → semua menu
        'OWN' => ['Owner', 'Super Admin', 'Admin'],
        'SM'  => ['Sales Manager'],
        'SLS' => ['Sales', 'Customer Service'],
        'KSR' => ['Kasir'],
        'ADP' => ['Admin Pengiriman', 'Delivery Driver'],
        'GDG' => ['Admin Inventory', 'Inventory Manager', 'Warehouse Staff', 'Checker'],
        'PUR' => ['Purchasing', 'Purchasing Manager'],
        // Auditor ikut kelompok Finance untuk melihat; permission-nya tetap hanya "view any".
        'FIN' => ['Finance Manager', 'Accounting', 'Admin Keuangan', 'Auditor'],
        // Tim teknis: hanya menu Pengaturan pengguna.
        'IT'  => ['IT Support'],
    ],

    // Kode peran yang selalu boleh membuka semua menu.
    'super_codes' => ['OWN'],

    'menus' => [
        // BERANDA
        Pages\Dashboard::class => ['OWN', 'SM', 'SLS', 'KSR', 'ADP', 'GDG', 'PUR', 'FIN', 'IT'],

        // PENJUALAN
        Resources\QuotationResource::class       => ['OWN', 'SM', 'SLS'],
        Resources\SaleOrderResource::class       => ['OWN', 'SM', 'SLS', 'KSR', 'ADP'],
        Resources\DeliveryOrderResource::class   => ['OWN', 'ADP', 'SLS', 'KSR'],
        Resources\SuratJalanResource::class      => ['OWN', 'ADP'],
        Resources\SalesInvoiceResource::class    => ['OWN', 'KSR', 'ADP', 'FIN'],
        Resources\CustomerReceiptResource::class => ['OWN', 'KSR', 'FIN'],
        Resources\CustomerReturnResource::class  => ['OWN', 'SM', 'SLS', 'GDG', 'FIN'],
        Resources\ReturnProductResource::class   => ['OWN', 'SLS', 'GDG', 'ADP'],
        Resources\DeliveryScheduleResource::class => ['OWN', 'ADP'],
        Resources\DeliveryOrderApprovalLogResource::class => ['OWN', 'ADP'],

        // GUDANG
        Resources\WarehouseConfirmationResource::class => ['OWN', 'GDG'],
        Resources\PurchaseReceiptResource::class       => ['OWN', 'GDG', 'PUR'],
        Resources\PurchaseReceiptItemResource::class   => ['OWN', 'GDG', 'PUR'],
        Resources\QualityControlPurchaseResource::class => ['OWN', 'GDG', 'PUR'],
        Resources\InventoryStockResource::class        => ['OWN', 'SM', 'SLS', 'KSR', 'ADP', 'GDG', 'PUR', 'FIN'],
        Resources\StockTransferResource::class         => ['OWN', 'GDG'],
        Resources\StockOpnameResource::class           => ['OWN', 'GDG'],
        Resources\StockAdjustmentResource::class       => ['OWN', 'GDG', 'FIN'],
        // Tahap 3 — layar HP gudang
        Pages\Hp\KirimanPage::class                    => ['OWN', 'GDG'],
        Pages\Hp\KirimanDetailPage::class              => ['OWN', 'GDG'],
        Pages\Hp\ReturDoPage::class                    => ['OWN', 'GDG', 'SLS', 'ADP'],
        Pages\Hp\OpnameHpPage::class                   => ['OWN', 'GDG'],
        Pages\Hp\CekStokHpPage::class                  => ['OWN', 'SM', 'SLS', 'KSR', 'ADP', 'GDG', 'PUR', 'FIN'],

        // PEMBELIAN
        Resources\OrderRequestResource::class    => ['OWN', 'PUR', 'GDG', 'SLS'],
        Resources\PurchaseOrderResource::class   => ['OWN', 'PUR'],
        Resources\PurchaseInvoiceResource::class => ['OWN', 'PUR', 'FIN'],
        Resources\VendorPaymentResource::class   => ['OWN', 'FIN'],
        Resources\PurchaseReturnResource::class  => ['OWN', 'PUR', 'GDG'],

        // KEUANGAN
        Resources\CashBankTransactionResource::class => ['OWN', 'FIN', 'KSR'],
        Resources\DepositResource::class             => ['OWN', 'FIN', 'KSR'],
        Resources\DepositAdjustmentResource::class   => ['OWN', 'FIN'],
        Resources\CashBankTransferResource::class    => ['OWN', 'FIN'],
        Resources\CashBankAccountResource::class     => ['OWN', 'FIN'],
        Resources\AccountReceivableResource::class   => ['OWN', 'FIN', 'KSR'],
        Resources\AccountPayableResource::class      => ['OWN', 'FIN'],
        Resources\AgeingScheduleResource::class      => ['OWN', 'FIN'],
        Pages\ArApManagementPage::class              => ['OWN', 'FIN'],
        Resources\PaymentRequestResource::class      => ['OWN', 'FIN', 'PUR'],
        Resources\VoucherRequestResource::class      => ['OWN', 'FIN'],
        Resources\JournalEntryResource::class        => ['OWN', 'FIN'],
        Resources\JournalEntryResource\Pages\GroupedJournalEntries::class => ['OWN', 'FIN'],
        Resources\BankReconciliationResource::class  => ['OWN', 'FIN'],
        Resources\AssetResource::class               => ['OWN', 'FIN'],
        Resources\AssetTransferResource::class       => ['OWN', 'FIN'],
        Resources\AssetDisposalResource::class       => ['OWN', 'FIN'],
        Resources\AccountingPeriodResource::class    => ['OWN', 'FIN'],
        Resources\OtherSaleResource::class           => ['OWN', 'FIN'],
        Resources\CreditNoteResource::class          => ['OWN', 'FIN'],
        Resources\InvoiceResource::class             => ['OWN', 'FIN', 'KSR'],

        // LAPORAN
        Pages\MyDashboard::class        => ['OWN', 'FIN'],
        Pages\SalesReportPage::class    => ['OWN', 'SM', 'FIN'],
        Pages\PurchaseReportPage::class => ['OWN', 'PUR', 'FIN'],
        Pages\InventoryReportPage::class => ['OWN', 'GDG', 'PUR', 'FIN'],
        Resources\StockMovementResource::class => ['OWN', 'GDG', 'FIN'],
        Resources\Reports\StockReportResource::class => ['OWN', 'GDG', 'PUR', 'FIN'],
        Resources\Reports\InventoryCardResource::class => ['OWN', 'GDG', 'FIN'],
        Resources\Reports\StockMutationReportResource::class => ['OWN', 'GDG', 'FIN'],
        Pages\FinancialStatementPage::class => ['OWN', 'FIN'],
        Pages\BalanceSheetPage::class       => ['OWN', 'FIN'],
        Pages\IncomeStatementPage::class    => ['OWN', 'FIN'],
        Pages\TrialBalancePage::class       => ['OWN', 'FIN'],
        Pages\BukuBesarPage::class          => ['OWN', 'FIN'],
        Pages\DrillDownFinancialReportPage::class => ['OWN', 'FIN'],
        Pages\ProfitLossMultiDivisionPage::class  => ['OWN', 'FIN'],
        Pages\CostOfGoodsManufacturingPage::class => ['OWN', 'FIN'],
        Pages\AlkGraficPage::class          => ['OWN', 'FIN'],
        Pages\JournalConsolidationPage::class => ['OWN', 'FIN'],
        Pages\ViewAgeingReport::class       => ['OWN', 'FIN'],
        Resources\Reports\BalanceSheetResource::class   => ['OWN', 'FIN'],
        Resources\Reports\ProfitAndLossResource::class  => ['OWN', 'FIN'],
        Resources\Reports\CashFlowResource::class       => ['OWN', 'FIN'],
        Resources\Reports\HppResource::class            => ['OWN', 'FIN'],
        Resources\Reports\AgeingReportResource::class   => ['OWN', 'FIN'],

        // DATA MASTER
        Resources\ProductResource::class         => ['OWN', 'PUR'],
        Resources\ProductCategoryResource::class => ['OWN'],
        Resources\UnitOfMeasureResource::class   => ['OWN'],
        Resources\WarehouseResource::class       => ['OWN'],
        Resources\RakResource::class             => ['OWN'],
        Resources\CabangResource::class          => ['OWN'],
        Resources\CustomerResource::class        => ['OWN', 'SM', 'SLS', 'KSR', 'FIN'],
        Resources\SupplierResource::class        => ['OWN', 'PUR', 'FIN'],
        Resources\VehicleResource::class         => ['OWN', 'ADP'],
        Resources\DriverResource::class          => ['OWN', 'ADP'],
        Resources\ChartOfAccountResource::class  => ['OWN', 'FIN'],
        Resources\CurrencyResource::class        => ['OWN', 'FIN'],
        Resources\TaxSettingResource::class      => ['OWN', 'FIN'],

        // PENGATURAN
        Resources\UserResource::class        => ['OWN', 'IT'],
        Resources\RoleResource::class        => ['OWN', 'IT'],
        Resources\PermissionResource::class  => ['OWN', 'IT'],
        Resources\ApprovalRuleResource::class => ['OWN'],
        Pages\ModulAktifPage::class          => ['OWN'],
        Pages\AccountingSettingsPage::class  => ['OWN', 'FIN'],
        Pages\AppSettingsPage::class         => ['OWN'],
        Resources\LegacyTransactionArchiveResource::class => ['OWN'],
        Resources\HelpVideoResource::class  => ['OWN'],

        // PRODUKSI (hanya tampil bila modul manufaktur aktif)
        Resources\BillOfMaterialResource::class            => ['OWN'],
        Resources\ProductionPlanResource::class            => ['OWN'],
        Resources\ManufacturingOrderResource::class        => ['OWN'],
        Resources\MaterialIssueResource::class             => ['OWN'],
        Resources\ProductionResource::class                => ['OWN'],
        Resources\QualityControlManufactureResource::class => ['OWN'],
    ],

    'modules' => [
        'manufacturing' => [
            'label'       => 'Produksi / Manufaktur',
            'description' => 'Bill of Material, Rencana Produksi, Perintah Produksi, Pengeluaran Bahan, Produksi, QC Produksi.',
            'default'     => false,
            'menus'       => [
                Resources\BillOfMaterialResource::class,
                Resources\ProductionPlanResource::class,
                Resources\ManufacturingOrderResource::class,
                Resources\MaterialIssueResource::class,
                Resources\ProductionResource::class,
                Resources\QualityControlManufactureResource::class,
            ],
        ],
        'delivery_schedule' => [
            'label'       => 'Jadwal Kirim',
            'description' => 'Penjadwalan pengiriman (rute, kendaraan, driver) di grup PENJUALAN.',
            'default'     => false,
            'menus'       => [
                Resources\DeliveryScheduleResource::class,
            ],
        ],
        'other_sales' => [
            'label'       => 'Penjualan Lainnya',
            'description' => 'Pencatatan penjualan di luar alur SO (contoh: jual aset/barang bekas) di grup KEUANGAN.',
            'default'     => false,
            'menus'       => [
                Resources\OtherSaleResource::class,
            ],
        ],
    ],
];
