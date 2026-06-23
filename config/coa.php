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

    // --- Product Master Defaults ---
    'product' => [
        'inventory_coa_id' => [
            'standard' => ['1140.01', '1140.10'],
            'manufacture' => ['1140.02', '1140.01'],
            'raw_material' => ['1-101', '1140.10', '1140.01'],
        ],
        'sales_coa_id' => ['4100.10'],
        'sales_return_coa_id' => ['4120.10'],
        'sales_discount_coa_id' => ['4110.10'],
        'goods_delivery_coa_id' => ['1140.20'],
        'cogs_coa_id' => ['5100.10'],
        'purchase_return_coa_id' => ['5120.10'],
        'unbilled_purchase_coa_id' => ['2100.10', '2190.10', '1180.01'],
        'temporary_procurement_coa_id' => ['1400.01', '1180.01'],
        'manufacturing_labor_coa_id' => ['5230', '6-201', '6-202'],
        'manufacturing_overhead_coa_id' => ['6000', '6-301', '6-302'],
    ],

    // --- Expenses ---
    'import_duty'            => '5130',    // Bea Masuk
    'general_expense'        => '6100',    // Beban Umum

];
