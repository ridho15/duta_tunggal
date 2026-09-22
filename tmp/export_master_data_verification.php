<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sources = [
    'inventory' => [
        'database' => 'inventory',
        'prefix' => 'knr',
    ],
    'inventory_cab' => [
        'database' => 'inventory_cab',
        'prefix' => 'dtm',
    ],
];

$legacyTable = static fn (string $sourceName, string $tableSuffix): string => sprintf(
    '%s.%s_%s',
    $sources[$sourceName]['database'],
    $sources[$sourceName]['prefix'],
    $tableSuffix,
);

$data = [
    'source_counts' => [
        'inventory' => [
            'customers' => DB::table($legacyTable('inventory', 'customers'))->count(),
            'suppliers' => DB::table($legacyTable('inventory', 'suppliers'))->count(),
            'categories' => DB::table($legacyTable('inventory', 'product_categories'))->count(),
            'products' => DB::table($legacyTable('inventory', 'products'))->count(),
            'stock_rows' => DB::table($legacyTable('inventory', 'inventories'))->count(),
        ],
        'inventory_cab' => [
            'customers' => DB::table($legacyTable('inventory_cab', 'customers'))->count(),
            'suppliers' => DB::table($legacyTable('inventory_cab', 'suppliers'))->count(),
            'categories' => DB::table($legacyTable('inventory_cab', 'product_categories'))->count(),
            'products' => DB::table($legacyTable('inventory_cab', 'products'))->count(),
            'stock_rows' => DB::table($legacyTable('inventory_cab', 'inventories'))->count(),
        ],
    ],
    'target_counts' => [
        'cabangs' => DB::table('cabangs')->count(),
        'warehouses' => DB::table('warehouses')->count(),
        'customers' => DB::table('customers')->count(),
        'suppliers' => DB::table('suppliers')->count(),
        'categories' => DB::table('product_categories')->count(),
        'uoms' => DB::table('unit_of_measures')->count(),
        'products' => DB::table('products')->count(),
        'inventory_stocks' => DB::table('inventory_stocks')->count(),
    ],
    'distributions' => [
        'customers_by_cabang' => DB::table('customers')
            ->selectRaw('cabang_id, COUNT(*) as total')
            ->groupBy('cabang_id')
            ->orderBy('cabang_id')
            ->get(),
        'suppliers_by_cabang' => DB::table('suppliers')
            ->selectRaw('cabang_id, COUNT(*) as total')
            ->groupBy('cabang_id')
            ->orderBy('cabang_id')
            ->get(),
        'products_by_cabang' => DB::table('products')
            ->selectRaw('cabang_id, COUNT(*) as total')
            ->groupBy('cabang_id')
            ->orderBy('cabang_id')
            ->get(),
        'stocks_by_warehouse' => DB::table('inventory_stocks')
            ->selectRaw('warehouse_id, COUNT(*) as total')
            ->groupBy('warehouse_id')
            ->orderBy('warehouse_id')
            ->get(),
    ],
    'samples' => [
        'customer_inventory' => DB::table('customers')
            ->where('cabang_id', 1)
            ->orderBy('code')
            ->first(['code', 'name', 'cabang_id']),
        'customer_inventory_cab' => DB::table('customers')
            ->where('cabang_id', 2)
            ->where('code', 'like', 'CAB-%')
            ->orderBy('code')
            ->first(['code', 'name', 'cabang_id']),
        'supplier_inventory' => DB::table('suppliers')
            ->where('cabang_id', 1)
            ->orderBy('code')
            ->first(['code', 'perusahaan', 'cabang_id']),
        'supplier_inventory_cab' => DB::table('suppliers')
            ->where('cabang_id', 2)
            ->where('code', 'like', 'CAB-%')
            ->orderBy('code')
            ->first(['code', 'perusahaan', 'cabang_id']),
        'product_inventory' => DB::table('products')
            ->where('cabang_id', 1)
            ->orderBy('sku')
            ->first(['sku', 'name', 'cabang_id']),
        'product_inventory_cab' => DB::table('products')
            ->where('cabang_id', 2)
            ->where('sku', 'like', 'CAB-%')
            ->orderBy('sku')
            ->first(['sku', 'name', 'cabang_id']),
        'stock_inventory' => DB::table('inventory_stocks')
            ->where('warehouse_id', 1)
            ->orderBy('id')
            ->first(['id', 'product_id', 'warehouse_id', 'qty_available', 'qty_reserved', 'qty_min']),
        'stock_inventory_cab' => DB::table('inventory_stocks')
            ->where('warehouse_id', 2)
            ->orderBy('id')
            ->first(['id', 'product_id', 'warehouse_id', 'qty_available', 'qty_reserved', 'qty_min']),
    ],
    'duplicate_customer_code_inventory' => DB::table($legacyTable('inventory', 'customers'))
        ->select('customer_code')
        ->groupBy('customer_code')
        ->havingRaw('COUNT(*) > 1')
        ->orderBy('customer_code')
        ->get(),
    'cabangs' => DB::table('cabangs')
        ->orderBy('id')
        ->get(['id', 'kode', 'nama']),
    'warehouses' => DB::table('warehouses')
        ->orderBy('id')
        ->get(['id', 'kode', 'name', 'cabang_id']),
    'auth_state' => [
        'users' => DB::table('users')->count(),
        'roles' => DB::table('roles')->count(),
        'permissions' => DB::table('permissions')->count(),
    ],
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;