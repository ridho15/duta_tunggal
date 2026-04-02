<?php

/**
 * Chart of Accounts (COA) code configuration.
 *
 * These are the default COA codes used as fallbacks when a document does not
 * specify a custom account. Centralising them here makes it easy to adapt to
 * different company configurations without changing service code.
 *
 * Usage example:
 *   ChartOfAccount::where('code', config('coa.inventory'))->first()
 */

return [

    // --- Assets ---
    'cash_and_bank'          => '1112.01', // Kas / Bank default
    'accounts_receivable'    => '1120',    // Piutang Dagang
    'inventory'              => '1140.01', // Persediaan Barang
    'ppn_masukan'            => '1170.06', // PPN Masukan
    'pph22'                  => '1170.02', // PPh Pasal 22
    'fixed_asset'            => '1500',    // Harga Perolehan Aset Tetap

    // --- Liabilities ---
    'accounts_payable'       => '2110',     // Hutang Dagang
    'unbilled_purchase'      => '2100.10',  // Pembelian Belum Tertagih
    'customer_deposit'       => '2160.04', // Deposit Pelanggan
    'sales_output_vat'       => '2120.06', // PPN Keluaran

    // --- Sales ---
    'sales_revenue'          => '4000',    // Penjualan
    'sales_discount'         => '4100.01', // Diskon Penjualan
    'sales_shipping'         => '6100.02', // Biaya Pengiriman Penjualan

    // --- Expenses ---
    'import_duty'            => '5130',    // Bea Masuk
    'general_expense'        => '6100',    // Beban Umum

];
