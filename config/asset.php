<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Chart of Account Codes untuk Aset Tetap
    |--------------------------------------------------------------------------
    |
    | Kode COA default yang digunakan ketika sebuah aset tidak memiliki
    | COA yang ditentukan secara spesifik. Sesuaikan dengan struktur
    | Chart of Accounts perusahaan.
    |
    */

    'coa' => [

        // Akun Aset Tetap (debit saat perolehan)
        'asset' => env('ASSET_COA_CODE', '1210.01'),

        // Akumulasi Penyusutan (kredit saat penyusutan) — Contra Asset
        'accumulated_depreciation' => env('ASSET_ACCUM_DEPRECIATION_COA_CODE', '1220.01'),

        // Beban Penyusutan (debit saat penyusutan)
        'depreciation_expense' => env('ASSET_DEPRECIATION_EXPENSE_COA_CODE', '6311'),

        // Hutang Usaha — untuk jurnal perolehan aset (kredit)
        'accounts_payable' => env('ASSET_AP_COA_CODE', '2110'),

        // Kas / Bank — untuk aset yang dibeli tunai (kredit)
        'cash' => env('ASSET_CASH_COA_CODE', '1100'),

        // Akun Pelepasan Aset / Disposal
        'disposal_gain' => env('ASSET_DISPOSAL_GAIN_COA_CODE', '7100'),
        'disposal_loss' => env('ASSET_DISPOSAL_LOSS_COA_CODE', '8100'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengaturan Penyusutan Otomatis
    |--------------------------------------------------------------------------
    */

    'depreciation' => [
        // Hari dalam sebulan untuk menjalankan penyusutan otomatis
        'monthly_day' => env('ASSET_DEPRECIATION_DAY', 1),

        // Metode penyusutan default jika tidak ditentukan pada aset
        'default_method' => env('ASSET_DEFAULT_DEPRECIATION_METHOD', 'straight_line'),
    ],

];
