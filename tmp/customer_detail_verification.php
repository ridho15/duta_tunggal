<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$inventoryDuplicates = DB::table('inventory.knr_customers')
    ->select('customer_code', DB::raw('COUNT(*) as total'))
    ->groupBy('customer_code')
    ->havingRaw('COUNT(*) > 1')
    ->orderByDesc('total')
    ->get();

$inventoryCodes = DB::table('inventory.knr_customers')
    ->whereRaw("COALESCE(TRIM(customer_code), '') <> ''")
    ->distinct()
    ->pluck('customer_code');

$inventoryCabCodes = DB::table('inventory_cab.dtm_customers')
    ->whereRaw("COALESCE(TRIM(customer_code), '') <> ''")
    ->distinct()
    ->pluck('customer_code');

$targetCab1Codes = DB::table('customers')
    ->where('cabang_id', 1)
    ->whereRaw("COALESCE(TRIM(code), '') <> ''")
    ->pluck('code');

$targetCab2Codes = DB::table('customers')
    ->where('cabang_id', 2)
    ->whereRaw("COALESCE(TRIM(code), '') <> ''")
    ->pluck('code');

$missingInventoryCodes = $inventoryCodes
    ->reject(fn ($code) => $targetCab1Codes->contains($code))
    ->values();

$missingInventoryCabCodes = $inventoryCabCodes
    ->reject(fn ($code) => $targetCab2Codes->contains('CAB-' . $code))
    ->values();

$result = [
    'inventory' => [
        'source_rows' => DB::table('inventory.knr_customers')->count(),
        'distinct_non_empty_codes' => $inventoryCodes->count(),
        'duplicate_code_groups' => $inventoryDuplicates->count(),
        'duplicate_codes' => $inventoryDuplicates->take(5)->values(),
        'target_rows_cabang_1' => DB::table('customers')->where('cabang_id', 1)->count(),
        'missing_target_codes' => $missingInventoryCodes->take(10)->values(),
    ],
    'inventory_cab' => [
        'source_rows' => DB::table('inventory_cab.dtm_customers')->count(),
        'distinct_non_empty_codes' => $inventoryCabCodes->count(),
        'duplicate_code_groups' => DB::table('inventory_cab.dtm_customers')
            ->select('customer_code')
            ->groupBy('customer_code')
            ->havingRaw('COUNT(*) > 1')
            ->count(),
        'target_rows_cabang_2' => DB::table('customers')->where('cabang_id', 2)->count(),
        'missing_target_codes' => $missingInventoryCabCodes->take(10)->values(),
    ],
    'target' => [
        'rows' => DB::table('customers')->count(),
        'duplicate_code_groups' => DB::table('customers')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->count(),
        'empty_code_rows' => DB::table('customers')->whereRaw("COALESCE(TRIM(code), '') = ''")->count(),
    ],
    'samples' => [
        'inventory_duplicate' => $inventoryDuplicates->first(),
        'target_sample_cab1' => DB::table('customers')->where('cabang_id', 1)->orderBy('code')->first(['code', 'name', 'perusahaan', 'tipe', 'cabang_id']),
        'target_sample_cab2' => DB::table('customers')->where('cabang_id', 2)->orderBy('code')->first(['code', 'name', 'perusahaan', 'tipe', 'cabang_id']),
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;