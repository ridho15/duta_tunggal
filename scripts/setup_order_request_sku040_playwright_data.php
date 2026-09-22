<?php
/**
 * Deterministic fixture for SKU-040 order request decimal tests.
 * Sets up OrderRequest ID 20 and OrderRequestItem ID 56.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

DB::transaction(function () {
    $testUser = DB::table('users')->where('email', 'ralamzah@gmail.com')->first();
    $userId = $testUser?->id ?? DB::table('users')->value('id') ?? 1;
    $cabangId = $testUser?->cabang_id ?? DB::table('cabangs')->value('id') ?? 1;
    $warehouseId = DB::table('warehouses')->where('cabang_id', $cabangId)->value('id')
        ?? DB::table('warehouses')->value('id')
        ?? 1;

    $product = DB::table('products')->where('sku', 'SKU-040')->first();
    if (!$product) {
        throw new \Exception("Product SKU-040 not found in database.");
    }

    $supplierId = $product->supplier_id ?? DB::table('suppliers')->value('id') ?? 1;

    // 1. Clean up existing ID 20 and ID 56
    DB::table('order_request_items')->where('id', 56)->delete();
    DB::table('order_request_items')->where('order_request_id', 20)->delete();
    DB::table('order_requests')->where('id', 20)->delete();

    // 2. Insert OrderRequest with ID 20
    $orData = [
        'id' => 20,
        'request_number' => 'OR-TEST-DECIMAL-SKU040',
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ];
    if (Schema::hasColumn('order_requests', 'warehouse_id')) {
        $orData['warehouse_id'] = $warehouseId;
    }
    if (Schema::hasColumn('order_requests', 'cabang_id')) {
        $orData['cabang_id'] = $cabangId;
    }
    DB::table('order_requests')->insert($orData);

    // 3. Insert OrderRequestItem with ID 56
    $itemData = [
        'id' => 56,
        'order_request_id' => 20,
        'product_id' => $product->id,
        'supplier_id' => $supplierId,
        'quantity' => 1,
        'fulfilled_quantity' => 0,
        'currency_id' => 2, // USD
        'original_price' => 30.00,
        'unit_price' => 29.80,
        'discount' => 0,
        'tax' => 0,
        'subtotal' => 29.80,
        'tipe_pajak' => 'none',
        'created_at' => now(),
        'updated_at' => now(),
    ];
    if (Schema::hasColumn('order_request_items', 'cabang_id')) {
        $itemData['cabang_id'] = $cabangId;
    }
    DB::table('order_request_items')->insert($itemData);

    echo "✅ Deterministic OR ID 20 and Item ID 56 seeded successfully for SKU-040 tests.\n";
});
