<?php
/**
 * Deterministic fixture for Playwright Quality Control Purchase tests.
 *
 * Creates:
 * - 1 dedicated branch (Cabang)
 * - 1 dedicated supplier
 * - 1 dedicated product
 * - 1 approved purchase order in that branch
 * - 1 purchase order item for that PO
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$now = now();

$fixture = [
    'cabang_nama' => 'Cabang E2E Playwright',
    'supplier_code' => 'SUPP-E2E-QC',
    'supplier_name' => 'PT Supplier E2E QC',
    'po_number' => 'PO-E2E-QC-TEST',
];

DB::transaction(function () use ($now, $fixture) {
    // 1. Find or create test user
    $testUser = DB::table('users')->where('email', 'ralamzah@gmail.com')->first();
    $userId = $testUser?->id ?? DB::table('users')->value('id') ?? 1;

    // 2. Create branch
    DB::table('cabangs')->updateOrInsert(
        ['nama' => $fixture['cabang_nama']],
        [
            'kode' => 'C-E2E',
            'status' => 1,
            'alamat' => 'Alamat E2E',
            'telepon' => '021123456',
            'warna_background' => '#ffffff',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $cabangId = (int) DB::table('cabangs')->where('nama', $fixture['cabang_nama'])->value('id');

    // 3. Create warehouse in that branch
    DB::table('warehouses')->updateOrInsert(
        ['kode' => 'W-E2E-QC'],
        [
            'name' => 'Gudang E2E QC',
            'cabang_id' => $cabangId,
            'status' => 1,
            'location' => 'Location E2E',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $warehouseId = (int) DB::table('warehouses')->where('kode', 'W-E2E-QC')->value('id');

    // Also create another warehouse in a different branch to test filtering
    DB::table('warehouses')->updateOrInsert(
        ['kode' => 'W-E2E-OTHER'],
        [
            'name' => 'Gudang E2E Lainnya',
            'cabang_id' => 9999, // dummy other cabang
            'status' => 1,
            'location' => 'Location E2E',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );

    // 4. Create supplier
    DB::table('suppliers')->updateOrInsert(
        ['code' => $fixture['supplier_code']],
        [
            'perusahaan' => $fixture['supplier_name'],
            'cabang_id' => $cabangId,
            'address' => 'Alamat Supplier E2E',
            'phone' => '081100000000',
            'handphone' => '081100000000',
            'fax' => '021123456',
            'npwp' => '12.345.678.9-012.000',
            'email' => 'supplier-e2e@example.com',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $supplierId = (int) DB::table('suppliers')->where('code', $fixture['supplier_code'])->value('id');

    // 5. Create product
    DB::table('products')->updateOrInsert(
        ['sku' => 'PROD-E2E-QC'],
        [
            'name' => 'Produk E2E QC',
            'uom_id' => DB::table('unit_of_measures')->value('id') ?? 1,
            'product_category_id' => DB::table('product_categories')->value('id') ?? 1,
            'kode_merk' => 'ASUS',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $productId = (int) DB::table('products')->where('sku', 'PROD-E2E-QC')->value('id');

    // 6. Cleanup existing PO
    $existingPoIds = DB::table('purchase_orders')
        ->where('po_number', $fixture['po_number'])
        ->pluck('id')
        ->toArray();
    if (!empty($existingPoIds)) {
        DB::table('purchase_order_items')->whereIn('purchase_order_id', $existingPoIds)->delete();
        DB::table('purchase_orders')->whereIn('id', $existingPoIds)->delete();
    }

    // 7. Create approved PurchaseOrder
    $poData = [
        'po_number' => $fixture['po_number'],
        'supplier_id' => $supplierId,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'expected_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 150000,
        'tempo_hutang' => 30,
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (Schema::hasColumn('purchase_orders', 'warehouse_id')) {
        $poData['warehouse_id'] = $warehouseId;
    }
    if (Schema::hasColumn('purchase_orders', 'cabang_id')) {
        $poData['cabang_id'] = $cabangId;
    }
    DB::table('purchase_orders')->insert($poData);
    $poId = (int) DB::table('purchase_orders')->where('po_number', $fixture['po_number'])->value('id');

    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $poId,
        'product_id' => $productId,
        'quantity' => 10,
        'unit_price' => 15000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'none',
        'currency_id' => DB::table('currencies')->value('id') ?? 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "✅ QC Purchase Playwright fixture ready\n";
});
