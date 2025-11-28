<?php

return [
    'raw_material_inventory_prefixes' => [
        '1140.01',
    ],

    'raw_material_purchase_prefixes' => [
        '5110',
    ],

    'direct_labor_prefixes' => [
        '5120',
    ],

    'overhead_items' => [
        [
            'key' => 'factory_electricity',
            'label' => 'Biaya Listrik Pabrik',
            'coa_prefixes' => ['5130'],
        ],
        [
            'key' => 'machine_depreciation',
            'label' => 'Biaya Penyusutan Mesin',
            'coa_prefixes' => ['5140'],
        ],
        [
            'key' => 'maintenance',
            'label' => 'Biaya Perawatan',
            'coa_prefixes' => ['5150'],
        ],
    ],

    'wip_inventory_prefixes' => [
        '1140.02',
    ],

    'cogs_account_codes' => [
        '5000',
        '5-1000',
    ],

    'cogs_account_prefixes' => [
        '5000',
        '5100',
    ],
];
