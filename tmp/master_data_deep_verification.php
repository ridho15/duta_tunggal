<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$legacyTables = [
    'inventory' => [
        'database' => 'inventory',
        'prefix' => 'knr',
    ],
    'inventory_cab' => [
        'database' => 'inventory_cab',
        'prefix' => 'dtm',
    ],
];

$legacyTable = static fn (string $source, string $suffix): string => sprintf(
    '%s.%s_%s',
    $legacyTables[$source]['database'],
    $legacyTables[$source]['prefix'],
    $suffix,
);

$result = [
    'suppliers' => [],
    'products' => [],
    'inventory_stocks' => [],
];

foreach (['inventory', 'inventory_cab'] as $source) {
    $sourcePrefix = $source === 'inventory' ? '' : 'CAB-';

    $supplierCodes = DB::table($legacyTable($source, 'suppliers'))
        ->whereRaw("COALESCE(TRIM(supplier_code), '') <> ''")
        ->distinct()
        ->pluck('supplier_code');

    $productCodes = DB::table($legacyTable($source, 'products'))
        ->whereRaw("COALESCE(TRIM(product_code), '') <> ''")
        ->distinct()
        ->pluck('product_code');

    $stockProducts = DB::table($legacyTable($source, 'products'))
        ->whereRaw("COALESCE(TRIM(product_code), '') <> ''")
        ->distinct()
        ->pluck('product_code');

    if ($source === 'inventory') {
        $targetSupplierCodes = DB::table('suppliers')
            ->where('cabang_id', 1)
            ->whereRaw("COALESCE(TRIM(code), '') <> ''")
            ->pluck('code');

        $targetProductSkus = DB::table('products')
            ->where('cabang_id', 1)
            ->whereRaw("COALESCE(TRIM(sku), '') <> ''")
            ->pluck('sku');
    } else {
        $targetSupplierCodes = DB::table('suppliers')
            ->where('cabang_id', 2)
            ->whereRaw("COALESCE(TRIM(code), '') <> ''")
            ->pluck('code');

        $targetProductSkus = DB::table('products')
            ->where('cabang_id', 2)
            ->whereRaw("COALESCE(TRIM(sku), '') <> ''")
            ->pluck('sku');
    }

    $expectedTargetSupplierCodes = $supplierCodes->map(function ($code) use ($sourcePrefix) {
        return $sourcePrefix . $code;
    });

    $expectedTargetProductSkus = $productCodes->map(function ($code) use ($sourcePrefix) {
        return $sourcePrefix . $code;
    });

    $missingSupplierCodes = $expectedTargetSupplierCodes
        ->reject(fn ($code) => $targetSupplierCodes->contains($code))
        ->values();

    $missingProductSkus = $expectedTargetProductSkus
        ->reject(fn ($sku) => $targetProductSkus->contains($sku))
        ->values();

    $sourceDuplicateSupplierGroups = DB::table($legacyTable($source, 'suppliers'))
        ->select('supplier_code')
        ->groupBy('supplier_code')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    $sourceDuplicateProductGroups = DB::table($legacyTable($source, 'products'))
        ->select('product_code')
        ->groupBy('product_code')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    $targetDuplicateSupplierGroups = $source === 'inventory'
        ? DB::table('suppliers')->where('cabang_id', 1)->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->count()
        : DB::table('suppliers')->where('cabang_id', 2)->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->count();

    $targetDuplicateProductGroups = $source === 'inventory'
        ? DB::table('products')->where('cabang_id', 1)->select('sku')->groupBy('sku')->havingRaw('COUNT(*) > 1')->count()
        : DB::table('products')->where('cabang_id', 2)->select('sku')->groupBy('sku')->havingRaw('COUNT(*) > 1')->count();

    $result['suppliers'][$source] = [
        'source_rows' => DB::table($legacyTable($source, 'suppliers'))->count(),
        'distinct_non_empty_codes' => $supplierCodes->count(),
        'duplicate_code_groups' => $sourceDuplicateSupplierGroups,
        'target_rows' => $source === 'inventory'
            ? DB::table('suppliers')->where('cabang_id', 1)->count()
            : DB::table('suppliers')->where('cabang_id', 2)->count(),
        'target_duplicate_code_groups' => $targetDuplicateSupplierGroups,
        'missing_target_codes' => $missingSupplierCodes->take(10)->values(),
        'sample_source' => DB::table($legacyTable($source, 'suppliers'))->orderBy('id')->first(['supplier_code', 'supplier_name', 'supplier_company', 'supplier_contact']),
        'sample_target' => $source === 'inventory'
            ? DB::table('suppliers')->where('cabang_id', 1)->orderBy('code')->first(['code', 'perusahaan', 'kontak_person', 'cabang_id'])
            : DB::table('suppliers')->where('cabang_id', 2)->orderBy('code')->first(['code', 'perusahaan', 'kontak_person', 'cabang_id']),
    ];

    $result['products'][$source] = [
        'source_rows' => DB::table($legacyTable($source, 'products'))->count(),
        'distinct_non_empty_codes' => $productCodes->count(),
        'duplicate_code_groups' => $sourceDuplicateProductGroups,
        'target_rows' => $source === 'inventory'
            ? DB::table('products')->where('cabang_id', 1)->count()
            : DB::table('products')->where('cabang_id', 2)->count(),
        'target_duplicate_code_groups' => $targetDuplicateProductGroups,
        'missing_target_skus' => $missingProductSkus->take(10)->values(),
        'sample_source' => DB::table($legacyTable($source, 'products'))->orderBy('id')->first(['product_code', 'product_name', 'satuan', 'product_category_id']),
        'sample_target' => $source === 'inventory'
            ? DB::table('products')->where('cabang_id', 1)->orderBy('sku')->first(['sku', 'name', 'cabang_id'])
            : DB::table('products')->where('cabang_id', 2)->orderBy('sku')->first(['sku', 'name', 'cabang_id']),
    ];

    $result['inventory_stocks'][$source] = [
        'legacy_inventory_rows' => DB::table($legacyTable($source, 'inventories'))->count(),
        'legacy_stock_rules_rows' => DB::table($legacyTable($source, 'product_stocks'))->count(),
        'distinct_product_codes' => $stockProducts->count(),
        'target_rows' => $source === 'inventory'
            ? DB::table('inventory_stocks')->where('warehouse_id', 1)->count()
            : DB::table('inventory_stocks')->where('warehouse_id', 2)->count(),
        'missing_target_products' => $source === 'inventory'
            ? $stockProducts->reject(fn ($code) => DB::table('products')->where('cabang_id', 1)->where('sku', $code)->exists())->take(10)->values()
            : $stockProducts->reject(fn ($code) => DB::table('products')->where('cabang_id', 2)->where('sku', 'CAB-' . $code)->exists())->take(10)->values(),
        'sample_target' => $source === 'inventory'
            ? DB::table('inventory_stocks')->where('warehouse_id', 1)->orderBy('id')->first(['product_id', 'warehouse_id', 'qty_available', 'qty_reserved', 'qty_min'])
            : DB::table('inventory_stocks')->where('warehouse_id', 2)->orderBy('id')->first(['product_id', 'warehouse_id', 'qty_available', 'qty_reserved', 'qty_min']),
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;