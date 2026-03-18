<?php
/**
 * Deterministic fixture for Playwright Purchase Invoice tests (B1/B2/B3).
 *
 * Creates:
 * - 1 dedicated supplier fixture
 * - 1 completed PO fixture
 * - 2 completed receipts under that PO:
 *     - receipt #1 already invoiced (must be disabled in UI)
 *     - receipt #2 not invoiced (must be selectable in UI)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$now = now();

$fixture = [
    'supplier_code' => 'SUPP001',
    'supplier_name' => 'PT Supplier Utama',
    'po_number' => 'PO-TEST-INV-B23',
    'receipt_locked' => 'PR-TEST-INV-LOCKED',
    'receipt_open' => 'PR-TEST-INV-OPEN',
    'invoice_number' => 'INV-TEST-INV-LOCKED',
];

DB::transaction(function () use ($now, $fixture) {
    $testUser = DB::table('users')->where('email', 'ralamzah@gmail.com')->first();
    $userId = $testUser?->id ?? DB::table('users')->value('id') ?? 1;
    $cabangId = $testUser?->cabang_id ?? DB::table('cabangs')->value('id') ?? 1;
    $warehouseId = DB::table('warehouses')->where('cabang_id', $cabangId)->value('id')
        ?? DB::table('warehouses')->value('id')
        ?? 1;
    $currencyId = DB::table('currencies')->value('id') ?? 1;

    $productIds = DB::table('products')->orderBy('id')->limit(2)->pluck('id')->toArray();
    if (count($productIds) < 1) {
        throw new RuntimeException('No products found for fixture setup.');
    }
    $productId = (int) $productIds[0];

    $supplier = DB::table('suppliers')->where('code', $fixture['supplier_code'])->first();
    if (!$supplier) {
        $supplier = DB::table('suppliers')->orderBy('id')->first();
        if (!$supplier) {
            throw new RuntimeException('No supplier available for fixture setup.');
        }
        $fixture['supplier_code'] = $supplier->code;
        $fixture['supplier_name'] = $supplier->perusahaan;
    }
    $supplierId = (int) $supplier->id;

    // Cleanup previous fixture chain
    $existingInvoiceIds = DB::table('invoices')
        ->where('invoice_number', 'like', 'INV-TEST-INV-%')
        ->pluck('id')
        ->toArray();
    if (!empty($existingInvoiceIds)) {
        DB::table('invoice_items')->whereIn('invoice_id', $existingInvoiceIds)->delete();
        DB::table('invoices')->whereIn('id', $existingInvoiceIds)->delete();
    }

    $existingReceiptIds = DB::table('purchase_receipts')
        ->whereIn('receipt_number', [$fixture['receipt_locked'], $fixture['receipt_open']])
        ->pluck('id')
        ->toArray();
    if (!empty($existingReceiptIds)) {
        DB::table('purchase_receipt_biayas')->whereIn('purchase_receipt_id', $existingReceiptIds)->delete();
        DB::table('purchase_receipt_items')->whereIn('purchase_receipt_id', $existingReceiptIds)->delete();
        DB::table('purchase_receipts')->whereIn('id', $existingReceiptIds)->delete();
    }

    $existingPoIds = DB::table('purchase_orders')
        ->where('po_number', $fixture['po_number'])
        ->pluck('id')
        ->toArray();
    if (!empty($existingPoIds)) {
        DB::table('purchase_order_items')->whereIn('purchase_order_id', $existingPoIds)->delete();
        DB::table('purchase_orders')->whereIn('id', $existingPoIds)->delete();
    }

    // Create completed PO
    $poId = DB::table('purchase_orders')->insertGetId([
        'supplier_id' => $supplierId,
        'po_number' => $fixture['po_number'],
        'order_date' => now()->toDateString(),
        'status' => 'completed',
        'expected_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 1000000,
        'warehouse_id' => $warehouseId,
        'tempo_hutang' => 30,
        'created_by' => $userId,
        'refer_model_type' => null,
        'refer_model_id' => null,
        'cabang_id' => $cabangId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $poItemId = DB::table('purchase_order_items')->insertGetId([
        'purchase_order_id' => $poId,
        'product_id' => $productId,
        'quantity' => 10,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Eklusif',
        'currency_id' => $currencyId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Receipt #1 (locked - already invoiced)
    $receiptLockedId = DB::table('purchase_receipts')->insertGetId([
        'receipt_number' => $fixture['receipt_locked'],
        'purchase_order_id' => $poId,
        'receipt_date' => now()->toDateString(),
        'received_by' => $userId,
        'currency_id' => $currencyId,
        'status' => 'completed',
        'cabang_id' => $cabangId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('purchase_receipt_items')->insert([
        'purchase_receipt_id' => $receiptLockedId,
        'purchase_order_item_id' => $poItemId,
        'product_id' => $productId,
        'qty_received' => 4,
        'qty_accepted' => 4,
        'qty_rejected' => 0,
        'warehouse_id' => $warehouseId,
        'status' => 'completed',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Receipt #2 (open - selectable)
    $receiptOpenId = DB::table('purchase_receipts')->insertGetId([
        'receipt_number' => $fixture['receipt_open'],
        'purchase_order_id' => $poId,
        'receipt_date' => now()->toDateString(),
        'received_by' => $userId,
        'currency_id' => $currencyId,
        'status' => 'completed',
        'cabang_id' => $cabangId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('purchase_receipt_items')->insert([
        'purchase_receipt_id' => $receiptOpenId,
        'purchase_order_item_id' => $poItemId,
        'product_id' => $productId,
        'qty_received' => 6,
        'qty_accepted' => 6,
        'qty_rejected' => 0,
        'warehouse_id' => $warehouseId,
        'status' => 'completed',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Existing invoice that locks receipt #1
    $invoiceId = DB::table('invoices')->insertGetId([
        'invoice_number' => $fixture['invoice_number'],
        'from_model_type' => 'App\\Models\\PurchaseOrder',
        'from_model_id' => $poId,
        'invoice_date' => now()->toDateString(),
        'subtotal' => 400000,
        'tax' => 0,
        'other_fee' => json_encode([]),
        'total' => 444000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => 'draft',
        'ppn_rate' => 11,
        'dpp' => 400000,
        'supplier_name' => $fixture['supplier_name'],
        'supplier_phone' => '081100000001',
        'purchase_receipts' => json_encode([$receiptLockedId]),
        'purchase_order_ids' => json_encode([$poId]),
        'cabang_id' => $cabangId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('invoice_items')->insert([
        'invoice_id' => $invoiceId,
        'product_id' => $productId,
        'quantity' => 4,
        'price' => 100000,
        'discount' => 0,
        'tax_rate' => 11,
        'tax_amount' => 44000,
        'subtotal' => 400000,
        'total' => 444000,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "✅ Purchase invoice fixture ready\n";
    echo "   Supplier : {$fixture['supplier_name']} ({$fixture['supplier_code']})\n";
    echo "   PO       : {$fixture['po_number']}\n";
    echo "   Locked   : {$fixture['receipt_locked']} (already invoiced)\n";
    echo "   Open     : {$fixture['receipt_open']} (selectable)\n";
});
