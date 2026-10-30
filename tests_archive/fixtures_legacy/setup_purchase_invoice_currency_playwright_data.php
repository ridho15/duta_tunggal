<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$now = now();

DB::transaction(function () use ($now) {
    $supplier = DB::table('suppliers')->first();
    if (!$supplier) {
        throw new RuntimeException('No supplier present');
    }
    $productId = DB::table('products')->value('id') ?? 1;
    $purchaseOrderId = DB::table('purchase_orders')->value('id') ?? 0;

    // Ensure currencies exist
    DB::table('currencies')->updateOrInsert(['code' => 'USD'], ['name' => 'USD', 'symbol' => '$', 'to_rupiah' => 16000, 'updated_at' => $now, 'created_at' => $now]);
    DB::table('currencies')->updateOrInsert(['code' => 'JPY'], ['name' => 'JPY', 'symbol' => '¥', 'to_rupiah' => 110, 'updated_at' => $now, 'created_at' => $now]);

    // Insert minimal invoice records (IDR totals already converted)
    $invoiceUsdNumber = 'INV-PLAY-USD';
    $totalUsd = 5 * 16000; // 80000
    DB::table('invoices')->updateOrInsert(
        ['invoice_number' => $invoiceUsdNumber],
        ['from_model_type' => 'App\\Models\\PurchaseOrder', 'from_model_id' => $purchaseOrderId, 'invoice_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'subtotal' => $totalUsd, 'tax' => 0, 'other_fee' => json_encode([]), 'total' => $totalUsd, 'status' => 'draft', 'ppn_rate' => 0, 'dpp' => $totalUsd, 'supplier_name' => $supplier->perusahaan ?? null, 'created_at' => $now, 'updated_at' => $now]
    );
    $invoiceUsdId = DB::table('invoices')->where('invoice_number', $invoiceUsdNumber)->value('id');
    DB::table('invoice_items')->updateOrInsert(
        ['invoice_id' => $invoiceUsdId, 'product_id' => $productId],
        ['quantity' => 1, 'price' => $totalUsd, 'subtotal' => $totalUsd, 'total' => $totalUsd, 'created_at' => $now, 'updated_at' => $now]
    );

    $invoiceJpyNumber = 'INV-PLAY-JPY';
    $totalJpy = 500 * 110; // 55000
    DB::table('invoices')->updateOrInsert(
        ['invoice_number' => $invoiceJpyNumber],
        ['from_model_type' => 'App\\Models\\PurchaseOrder', 'from_model_id' => $purchaseOrderId, 'invoice_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'subtotal' => $totalJpy, 'tax' => 0, 'other_fee' => json_encode([]), 'total' => $totalJpy, 'status' => 'draft', 'ppn_rate' => 0, 'dpp' => $totalJpy, 'supplier_name' => $supplier->perusahaan ?? null, 'created_at' => $now, 'updated_at' => $now]
    );
    $invoiceJpyId = DB::table('invoices')->where('invoice_number', $invoiceJpyNumber)->value('id');
    DB::table('invoice_items')->updateOrInsert(
        ['invoice_id' => $invoiceJpyId, 'product_id' => $productId],
        ['quantity' => 1, 'price' => $totalJpy, 'subtotal' => $totalJpy, 'total' => $totalJpy, 'created_at' => $now, 'updated_at' => $now]
    );

    echo "✅ Currency purchase invoice fixtures ready\n";
    echo "   USD invoice: {$invoiceUsdNumber} total={$totalUsd}\n";
    echo "   JPY invoice: {$invoiceJpyNumber} total={$totalJpy}\n";
});
