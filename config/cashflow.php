<?php

return [
    'cash_account_prefixes' => [
        '1111', // Kas
        '1112', // Bank
        '1113', // Deposito
    ],

    'categories' => [
        'operating' => [
            [
                'key' => 'cash_receipts_from_sales',
                'label' => 'Penerimaan Kas dari Penjualan',
                'type' => 'inflow',
                'resolver' => 'salesReceipts',
            ],
            [
                'key' => 'non_operating_income',
                'label' => 'Pendapatan Luar Usaha & Beban atas Pendapatan',
                'type' => 'net',
                'coa_prefixes' => ['7000'],
            ],
            [
                'key' => 'selling_expenses',
                'label' => 'Biaya Penjualan',
                'type' => 'outflow',
                'coa_prefixes' => ['6100'],
            ],
            [
                'key' => 'insurance_expenses',
                'label' => 'Biaya Asuransi',
                'type' => 'outflow',
                'coa_prefixes' => ['6220'],
            ],
            [
                'key' => 'rent_expenses',
                'label' => 'Biaya Sewa',
                'type' => 'outflow',
                'coa_prefixes' => ['6230'],
            ],
            [
                'key' => 'lgat_expenses',
                'label' => 'Biaya LGAT',
                'type' => 'outflow',
                'coa_prefixes' => ['6240'],
            ],
            [
                'key' => 'office_supplies_expenses',
                'label' => 'Biaya Perlengkapan Kantor',
                'type' => 'outflow',
                'coa_prefixes' => ['6250'],
                'include_assets' => true,
                'asset_coa_prefixes' => ['1210.01', '1210.02'],
            ],
            [
                'key' => 'office_needs_expenses',
                'label' => 'Biaya Keperluan Kantor',
                'type' => 'outflow',
                'coa_prefixes' => ['6260'],
            ],
            [
                'key' => 'special_services_expenses',
                'label' => 'Biaya Jasa Khusus',
                'type' => 'outflow',
                'coa_prefixes' => ['6270'],
            ],
            [
                'key' => 'general_admin_expenses',
                'label' => 'Biaya Administrasi Umum',
                'type' => 'outflow',
                'coa_prefixes' => ['6280'],
            ],
            [
                'key' => 'non_operating_expenses',
                'label' => 'Biaya Diluar Usaha',
                'type' => 'outflow',
                'coa_prefixes' => ['8000'],
            ],
        ],
        'investing' => [
            [
                'key' => 'asset_operations',
                'label' => 'Operasi Aset',
                'type' => 'net',
                'coa_prefixes' => ['1210', '1220', '1230', '1240', '1250'],
                'include_assets' => true,
                'asset_coa_prefixes' => ['1210', '1220', '1230', '1240', '1250'],
            ],
        ],
        'financing' => [
            [
                'key' => 'liability_operations',
                'label' => 'Operasi Liabilitas',
                'type' => 'net',
                'coa_prefixes' => ['2100', '2110', '2120', '2130', '2140', '2150', '2160', '2170', '2200', '2210', '2220'],
            ],
        ],
    ],
];
