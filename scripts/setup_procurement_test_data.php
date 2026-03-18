<?php
/**
 * Setup Order Request test data for E2E procurement tests.
 * Creates:
 *   - product_supplier catalog prices for products
 *   - OR #1: status=draft (1 item, supplier A)
 *   - OR #2: status=request_approve (1 item, supplier A, different item supplier_id)
 *   - OR #3: status=request_approve (3 items, 2 suppliers - to test grouping)
 *   - OR #4: status=approved (already approved)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;

$user = User::where('email', 'ralamzah@gmail.com')->first();
if (!$user) {
    die("ERROR: User ralamzah@gmail.com not found. Run setup_e2e_test_user.php first.\n");
}
$userId = $user->id;

// Get IDs
$warehouseId = DB::table('warehouses')->where('cabang_id', 1)->value('id') ?? 1;
$cabangId = 1;

// Use known products
$productIds = DB::table('products')->pluck('id')->toArray();
$productId1 = $productIds[0] ?? 1; // Panel Kontrol Industri
$productId2 = $productIds[1] ?? 2; // Sensor Tekanan Digital
$productId3 = $productIds[2] ?? 3; // Bahan Baku Plastik Granul

// Use known suppliers
$supplierIds = DB::table('suppliers')->pluck('id')->toArray();
$supplierA = $supplierIds[0] ?? 1; // SUPP001
$supplierB = $supplierIds[1] ?? 2; // SUPP002

echo "Using: warehouse=$warehouseId, cabang=$cabangId\n";
echo "Products: $productId1, $productId2, $productId3\n";
echo "Suppliers: A=$supplierA, B=$supplierB\n\n";

// 1. Setup product_supplier catalog prices
$catalogData = [
    ['product_id' => $productId1, 'supplier_id' => $supplierA, 'supplier_price' => 150000, 'created_at' => now(), 'updated_at' => now()],
    ['product_id' => $productId1, 'supplier_id' => $supplierB, 'supplier_price' => 160000, 'created_at' => now(), 'updated_at' => now()],
    ['product_id' => $productId2, 'supplier_id' => $supplierA, 'supplier_price' => 250000, 'created_at' => now(), 'updated_at' => now()],
    ['product_id' => $productId2, 'supplier_id' => $supplierB, 'supplier_price' => 240000, 'created_at' => now(), 'updated_at' => now()],
    ['product_id' => $productId3, 'supplier_id' => $supplierA, 'supplier_price' => 75000, 'created_at' => now(), 'updated_at' => now()],
    ['product_id' => $productId3, 'supplier_id' => $supplierB, 'supplier_price' => 80000, 'created_at' => now(), 'updated_at' => now()],
];
DB::table('product_supplier')->upsert(
    $catalogData,
    ['product_id', 'supplier_id'],
    ['supplier_price', 'updated_at']
);
echo "✅ Catalog prices set\n";

// Clean up existing test ORs
DB::table('order_request_items')->whereIn('order_request_id', function($q) {
    $q->select('id')->from('order_requests')->where('request_number', 'like', 'OR-TEST-%');
})->delete();
DB::table('order_requests')->where('request_number', 'like', 'OR-TEST-%')->delete();

$now = now();

// 2. OR #A: status=draft, 1 item
$orA = DB::table('order_requests')->insertGetId([
    'request_number' => 'OR-TEST-A-DRAFT',
    'warehouse_id'   => $warehouseId,
    'cabang_id'      => $cabangId,
    'request_date'   => now()->toDateString(),
    'status'         => 'draft',
    'created_by'     => $userId,
    'created_at'     => $now,
    'updated_at'     => $now,
]);
DB::table('order_request_items')->insert([
    'order_request_id'   => $orA,
    'product_id'         => $productId1,
    'supplier_id'        => null,     // no per-item supplier (old style)
    'quantity'           => 5,
    'fulfilled_quantity' => 0,
    'unit_price'         => 150000,   // matches catalog price
    'original_price'     => 150000,
    'discount'           => 0,
    'tax'                => 11,
    'subtotal'           => 832500,   // 5 * 150000 * 1.11
    'created_at'         => $now,
    'updated_at'         => $now,
]);
echo "✅ OR-TEST-A-DRAFT created (id=$orA)\n";

// 3. OR #B: status=request_approve, 1 item, item-level supplier_id, unit_price overridden
$orB = DB::table('order_requests')->insertGetId([
    'request_number' => 'OR-TEST-B-APPROVE',
    'warehouse_id'   => $warehouseId,
    'cabang_id'      => $cabangId,
    'request_date'   => now()->toDateString(),
    'status'         => 'request_approve',
    'created_by'     => $userId,
    'created_at'     => $now,
    'updated_at'     => $now,
]);
// unit_price = 130000 (user-overridden, LESS than catalog 150000)
// original_price = 150000 (catalog price at time of creation)
DB::table('order_request_items')->insert([
    'order_request_id'   => $orB,
    'product_id'         => $productId1,
    'supplier_id'        => $supplierA,  // per-item supplier
    'quantity'           => 10,
    'fulfilled_quantity' => 0,
    'unit_price'         => 130000,   // USER OVERRIDDEN price
    'original_price'     => 150000,   // catalog price
    'discount'           => 0,
    'tax'                => 11,
    'subtotal'           => 1443000,
    'created_at'         => $now,
    'updated_at'         => $now,
]);
echo "✅ OR-TEST-B-APPROVE (id=$orB) - 1 item (panel), unit_price=130000 (OVERRIDDEN, catalog=150000)\n";

// 4. OR #C: status=request_approve, 3 items, 2 different suppliers
// - Item 1: product1, supplier A, unit_price=150000 (catalog)
// - Item 2: product2, supplier B, unit_price=220000 (overridden, catalog=240000)
// - Item 3: product3, supplier A, unit_price=75000 (matches catalog)
$orC = DB::table('order_requests')->insertGetId([
    'request_number' => 'OR-TEST-C-MULTISUPPLIER',
    'warehouse_id'   => $warehouseId,
    'cabang_id'      => $cabangId,
    'request_date'   => now()->toDateString(),
    'status'         => 'request_approve',
    'created_by'     => $userId,
    'created_at'     => $now,
    'updated_at'     => $now,
]);
DB::table('order_request_items')->insert([
    [
        'order_request_id'   => $orC,
        'product_id'         => $productId1,
        'supplier_id'        => $supplierA,
        'quantity'           => 5,
        'fulfilled_quantity' => 0,
        'unit_price'         => 150000,
        'original_price'     => 150000,
        'discount'           => 0, 'tax' => 0,
        'subtotal'           => 750000,
        'created_at'         => $now, 'updated_at' => $now,
    ],
    [
        'order_request_id'   => $orC,
        'product_id'         => $productId2,
        'supplier_id'        => $supplierB,
        'quantity'           => 3,
        'fulfilled_quantity' => 0,
        'unit_price'         => 220000,   // OVERRIDE (catalog=240000)
        'original_price'     => 240000,
        'discount'           => 0, 'tax' => 0,
        'subtotal'           => 660000,
        'created_at'         => $now, 'updated_at' => $now,
    ],
    [
        'order_request_id'   => $orC,
        'product_id'         => $productId3,
        'supplier_id'        => $supplierA,
        'quantity'           => 20,
        'fulfilled_quantity' => 0,
        'unit_price'         => 75000,
        'original_price'     => 75000,
        'discount'           => 0, 'tax' => 0,
        'subtotal'           => 1500000,
        'created_at'         => $now, 'updated_at' => $now,
    ],
]);
echo "✅ OR-TEST-C-MULTISUPPLIER (id=$orC) - 3 items: supplierA+supplierA+supplierB\n";

// 5. OR #D: status=approved (for testing create_po action)
$orD = DB::table('order_requests')->insertGetId([
    'request_number' => 'OR-TEST-D-APPROVED',
    'warehouse_id'   => $warehouseId,
    'cabang_id'      => $cabangId,
    'request_date'   => now()->toDateString(),
    'status'         => 'approved',
    'created_by'     => $userId,
    'created_at'     => $now,
    'updated_at'     => $now,
]);
DB::table('order_request_items')->insert([
    'order_request_id'   => $orD,
    'product_id'         => $productId2,
    'supplier_id'        => $supplierB,
    'quantity'           => 8,
    'fulfilled_quantity' => 0,
    'unit_price'         => 240000,
    'original_price'     => 240000,
    'discount'           => 0, 'tax' => 11,
    'subtotal'           => 2131200,
    'created_at'         => $now, 'updated_at' => $now,
]);
echo "✅ OR-TEST-D-APPROVED (id=$orD) - 1 item (sensor), supplier B, unit_price=240000\n";

// 6. Ensure deterministic OR id=3 for Playwright specs
$or3 = DB::table('order_requests')->where('id', 3)->first();
if ($or3) {
    DB::table('order_requests')->where('id', 3)->update([
        'request_number' => 'OR-TEST-C-MULTISUPPLIER',
        'warehouse_id'   => $warehouseId,
        'cabang_id'      => $cabangId,
        'request_date'   => now()->toDateString(),
        'status'         => 'request_approve',
        'created_by'     => $userId,
        'updated_at'     => $now,
    ]);
} else {
    DB::table('order_requests')->insert([
        'id'             => 3,
        'request_number' => 'OR-TEST-C-MULTISUPPLIER',
        'warehouse_id'   => $warehouseId,
        'cabang_id'      => $cabangId,
        'request_date'   => now()->toDateString(),
        'status'         => 'request_approve',
        'created_by'     => $userId,
        'created_at'     => $now,
        'updated_at'     => $now,
    ]);
}

DB::table('order_request_items')->where('order_request_id', 3)->delete();
DB::table('order_request_items')->insert([
    [
        'order_request_id'   => 3,
        'product_id'         => $productId1,
        'supplier_id'        => $supplierA,
        'quantity'           => 5,
        'fulfilled_quantity' => 0,
        'unit_price'         => 150000,
        'original_price'     => 150000,
        'discount'           => 0,
        'tax'                => 0,
        'subtotal'           => 750000,
        'created_at'         => $now,
        'updated_at'         => $now,
    ],
    [
        'order_request_id'   => 3,
        'product_id'         => $productId2,
        'supplier_id'        => $supplierB,
        'quantity'           => 3,
        'fulfilled_quantity' => 0,
        'unit_price'         => 220000,
        'original_price'     => 240000,
        'discount'           => 0,
        'tax'                => 0,
        'subtotal'           => 660000,
        'created_at'         => $now,
        'updated_at'         => $now,
    ],
    [
        'order_request_id'   => 3,
        'product_id'         => $productId3,
        'supplier_id'        => $supplierA,
        'quantity'           => 20,
        'fulfilled_quantity' => 0,
        'unit_price'         => 75000,
        'original_price'     => 75000,
        'discount'           => 0,
        'tax'                => 0,
        'subtotal'           => 1500000,
        'created_at'         => $now,
        'updated_at'         => $now,
    ],
]);
echo "✅ OR deterministic fixture ensured at id=3 (request_approve, 3 items, 2 suppliers)\n";

echo "\n=== SUMMARY ===\n";
$ors = DB::table('order_requests')->where('request_number', 'like', 'OR-TEST-%')->get();
foreach ($ors as $or) {
    $items = DB::table('order_request_items')->where('order_request_id', $or->id)->get();
    echo "  #{$or->id} {$or->request_number} | status={$or->status}\n";
    foreach ($items as $item) {
        $prod = DB::table('products')->where('id', $item->product_id)->value('name');
        $sup = $item->supplier_id ? DB::table('suppliers')->where('id', $item->supplier_id)->value('perusahaan') : 'NULL';
        echo "    item#{$item->id}: [{$prod}] sup={$sup} | qty={$item->quantity} | unit_price={$item->unit_price} | original_price={$item->original_price}\n";
    }
}
