<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CREATING TEST DATA FOR PLAYWRIGHT ===\n";

// Use existing product
$product = \App\Models\Product::first();
if (!$product) {
    echo "No existing product found\n";
    exit;
}

echo "Using product: {$product->name} (SKU: {$product->sku})\n";

// Create a warehouse
$warehouse = \App\Models\Warehouse::firstOrCreate([
    'name' => 'Test Warehouse',
    'kode' => 'TW001',
], [
    'location' => 'Test Location',
    'cabang_id' => 1,
    'tipe' => 'Kecil',
    'telepon' => '021-1234567',
    'status' => 1,
]);

// Create a supplier
$supplier = \App\Models\Supplier::firstOrCreate([
    'name' => 'Test Supplier',
    'code' => 'TS001',
    'email' => 'test@supplier.com',
], [
    'perusahaan' => 'PT Test Supplier',
    'phone' => '123456789',
    'address' => 'Test Address',
    'handphone' => '081234567890',
    'fax' => '021-1234567',
    'npwp' => '0123456789012345',
    'tempo_hutang' => 30,
    'kontak_person' => 'Test Contact',
]);

// Create PO
$po = \App\Models\PurchaseOrder::create([
    'po_number' => 'PO-PLAYWRIGHT-' . date('Ymd-His'),
    'supplier_id' => $supplier->id,
    'warehouse_id' => $warehouse->id,
    'status' => 'approved',
    'order_date' => date('Y-m-d'),
    'tempo_hutang' => 30,
    'ppn_option' => 'standard',
    'is_asset' => 0,
    'is_import' => 0,
    'created_by' => 1,
    'approved_by' => 1,
    'date_approved' => date('Y-m-d'),
]);

// Create PO item
$poItem = \App\Models\PurchaseOrderItem::create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 10000,
    'currency_id' => 1,
]);

// Create QC for the PO item
$qc = \App\Models\QualityControl::create([
    'qc_number' => 'QC-PLAYWRIGHT-' . date('Ymd-His'),
    'from_model_type' => \App\Models\PurchaseOrderItem::class,
    'from_model_id' => $poItem->id,
    'product_id' => $product->id,
    'inspected_by' => 1,
    'warehouse_id' => $warehouse->id,
    'status' => 1, // completed
    'passed_quantity' => 10,
    'rejected_quantity' => 0,
    'notes' => 'Test QC for Playwright',
]);

echo "✅ Test data created successfully!\n";
echo "PO: {$po->po_number}\n";
echo "QC: {$qc->qc_number}\n";
echo "Product: {$product->name} (SKU: {$product->sku})\n";
echo "Supplier: {$supplier->name} ({$supplier->code})\n";
echo "Warehouse: {$warehouse->name} ({$warehouse->kode})\n";
