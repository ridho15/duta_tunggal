<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$invoiceId = DB::table('invoices')
    ->where('invoice_number', 'INV-TEST-INV-LOCKED')
    ->value('id');

$supplierId = DB::table('suppliers')
    ->where('code', 'SUPP001')
    ->value('id');

$orderRequestId = DB::table('order_requests')
    ->where('request_number', 'OR-TEST-INV-B23')
    ->value('id');

$purchaseOrderId = DB::table('purchase_orders')
    ->where('po_number', 'PO-TEST-INV-B23')
    ->value('id');

$receiptOpen = DB::table('purchase_receipts')
    ->where('receipt_number', 'PR-TEST-INV-OPEN')
    ->first();

$cabangId = null;
if ($purchaseOrderId && Schema::hasColumn('purchase_orders', 'cabang_id')) {
    $cabangId = DB::table('purchase_orders')
        ->where('id', $purchaseOrderId)
        ->value('cabang_id');
}

$cabangId ??= $receiptOpen?->cabang_id;

echo json_encode([
    'invoice_id' => $invoiceId ? (int) $invoiceId : null,
    'supplier_id' => $supplierId ? (int) $supplierId : null,
    'order_request_id' => $orderRequestId ? (int) $orderRequestId : null,
    'purchase_order_id' => $purchaseOrderId ? (int) $purchaseOrderId : null,
    'receipt_open_id' => $receiptOpen?->id ? (int) $receiptOpen->id : null,
    'cabang_id' => $cabangId ? (int) $cabangId : null,
]) . PHP_EOL;
