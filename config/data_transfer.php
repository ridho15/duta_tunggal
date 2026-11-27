<?php

use App\Models;

/*
| Export / Import Excel-CSV per halaman (khusus Owner / Super Admin, keputusan Ko Handy 27 Nov 2026).
| Kunci = kunci halaman di config/access.php.
|   model    : model utama halaman
|   date     : kolom tanggal untuk filter "dari / sampai" (opsional)
|   where_in : batasan data halaman, mis. invoice penjualan vs pembelian
|   import   : ['key' => kolom unik] bila data boleh diimpor (hanya data master); baris dengan kunci sama diperbarui
*/
return [
    'max_import_rows' => 5000,

    // Kolom yang tidak pernah ikut export / import
    'hidden_columns' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'deleted_at'],

    'datasets' => [
        'penjualan.quotations' => ['model' => Models\Quotation::class, 'date' => 'date'],
        'penjualan.so' => ['model' => Models\SaleOrder::class, 'date' => 'order_date'],
        'penjualan.do' => ['model' => Models\DeliveryOrder::class, 'date' => 'delivery_date'],
        'penjualan.sj' => ['model' => Models\SuratJalan::class, 'date' => 'issued_at'],
        'penjualan.schedules' => ['model' => Models\DeliverySchedule::class, 'date' => 'scheduled_date'],
        'penjualan.invoices' => ['model' => Models\Invoice::class, 'date' => 'invoice_date',
            'where_in' => ['from_model_type' => [Models\SuratJalan::class, Models\SaleOrder::class]]],
        'penjualan.receipts' => ['model' => Models\CustomerReceipt::class, 'date' => 'payment_date'],
        'penjualan.returns' => ['model' => Models\CustomerReturn::class, 'date' => 'return_date'],
        'penjualan.credit-notes' => ['model' => Models\CreditNote::class, 'date' => 'credit_date'],

        'gudang.penerimaan' => ['model' => Models\PurchaseReceipt::class, 'date' => 'receipt_date'],
        'gudang.stok' => ['model' => Models\InventoryStock::class],
        'gudang.transfer' => ['model' => Models\StockTransfer::class, 'date' => 'transfer_date'],
        'gudang.opname' => ['model' => Models\StockOpname::class, 'date' => 'opname_date'],
        'gudang.adjustment' => ['model' => Models\StockAdjustment::class, 'date' => 'adjustment_date'],

        'manufaktur.bom' => ['model' => Models\BillOfMaterial::class],
        'manufaktur.spk' => ['model' => Models\ManufacturingOrder::class, 'date' => 'start_date'],
        'manufaktur.material-issue' => ['model' => Models\MaterialIssue::class, 'date' => 'issue_date'],
        'manufaktur.production' => ['model' => Models\Production::class, 'date' => 'production_date'],
        'manufaktur.qc' => ['model' => Models\QualityControl::class],

        'pembelian.or' => ['model' => Models\OrderRequest::class, 'date' => 'request_date'],
        'pembelian.po' => ['model' => Models\PurchaseOrder::class, 'date' => 'order_date'],
        'pembelian.invoices' => ['model' => Models\Invoice::class, 'date' => 'invoice_date',
            'where_in' => ['from_model_type' => [Models\PurchaseOrder::class]]],
        'pembelian.payments' => ['model' => Models\VendorPayment::class, 'date' => 'payment_date'],
        'pembelian.returns' => ['model' => Models\PurchaseReturn::class, 'date' => 'return_date'],

        'keuangan.cashbank' => ['model' => Models\CashBankTransaction::class, 'date' => 'date'],
        'keuangan.journal' => ['model' => Models\JournalEntry::class, 'date' => 'date'],
        'keuangan.asset' => ['model' => Models\Asset::class, 'date' => 'purchase_date'],
        'master.coa' => ['model' => Models\ChartOfAccount::class, 'import' => ['key' => 'code']],

        'master.products' => ['model' => Models\Product::class, 'import' => ['key' => 'sku']],
        'master.customers' => ['model' => Models\Customer::class, 'import' => ['key' => 'code']],
        'master.suppliers' => ['model' => Models\Supplier::class, 'import' => ['key' => 'code']],
        'master.cabang' => ['model' => Models\Cabang::class],
        'master.gudang' => ['model' => Models\Warehouse::class],
        'master.kategori' => ['model' => Models\ProductCategory::class, 'import' => ['key' => 'kode']],
        'master.satuan' => ['model' => Models\UnitOfMeasure::class, 'import' => ['key' => 'name']],
        'master.kendaraan' => ['model' => Models\Vehicle::class, 'import' => ['key' => 'plate']],
        'master.mata-uang' => ['model' => Models\Currency::class, 'import' => ['key' => 'code']],
        'master.pajak' => ['model' => Models\TaxSetting::class],

        'settings.users' => ['model' => Models\User::class],
    ],
];
