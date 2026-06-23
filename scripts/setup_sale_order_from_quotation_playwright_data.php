<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $now = now();

    $user = DB::table('users')->where('email', 'ralamzah@gmail.com')->first()
        ?? DB::table('users')->orderBy('id')->first();
    if (! $user) {
        throw new RuntimeException('No users found for sale order quotation fixture setup');
    }

    $cabangId = $user->cabang_id ?? DB::table('cabangs')->value('id');
    if (! $cabangId) {
        throw new RuntimeException('No cabang found for sale order quotation fixture setup');
    }

    $customer = DB::table('customers')->where('cabang_id', $cabangId)->first()
        ?? DB::table('customers')->orderBy('id')->first();
    if (! $customer) {
        throw new RuntimeException('No customer found for sale order quotation fixture setup');
    }

    $categoryId = DB::table('product_categories')->value('id');
    $uomId = DB::table('unit_of_measures')->value('id');
    if (! $categoryId || ! $uomId) {
        throw new RuntimeException('Need at least one product category and unit of measure for fixture setup');
    }

    $warehouseA = DB::table('warehouses')->where('kode', 'PW-SO-STOCK-A')->first();
    if (! $warehouseA) {
        $warehouseAId = DB::table('warehouses')->insertGetId([
            'kode' => 'PW-SO-STOCK-A',
            'name' => 'Gudang PW Stock A',
            'cabang_id' => $cabangId,
            'location' => 'Fixture Location A',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $warehouseA = DB::table('warehouses')->where('id', $warehouseAId)->first();
    }

    $warehouseB = DB::table('warehouses')->where('kode', 'PW-SO-STOCK-B')->first();
    if (! $warehouseB) {
        $warehouseBId = DB::table('warehouses')->insertGetId([
            'kode' => 'PW-SO-STOCK-B',
            'name' => 'Gudang PW Stock B',
            'cabang_id' => $cabangId,
            'location' => 'Fixture Location B',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $warehouseB = DB::table('warehouses')->where('id', $warehouseBId)->first();
    }

    $productId = DB::table('products')->where('sku', 'PW-SO-QUOTE-001')->value('id');
    if (! $productId) {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'PW-SO-QUOTE-001',
            'name' => 'Produk PW Sales Order Quotation',
            'product_category_id' => $categoryId,
            'uom_id' => $uomId,
            'cabang_id' => $cabangId,
            'kode_merk' => 'PW-SO',
            'cost_price' => 100000,
            'sell_price' => 150000,
            'biaya' => 0,
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    DB::table('inventory_stocks')->where('product_id', $productId)->whereIn('warehouse_id', [$warehouseA->id, $warehouseB->id])->delete();

    DB::table('inventory_stocks')->insert([
        'product_id' => $productId,
        'warehouse_id' => $warehouseA->id,
        'qty_available' => 25,
        'qty_reserved' => 0,
        'qty_min' => 0,
        'rak_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    $quotationNumber = 'QT-PW-SO-001';
    $quotationId = DB::table('quotations')->where('quotation_number', $quotationNumber)->value('id');

    $quantity = 3;
    $unitPrice = 150000;
    $tax = 11;
    $totalAmount = $quantity * $unitPrice * 1.11;

    if ($quotationId) {
        DB::table('quotations')->where('id', $quotationId)->update([
            'customer_id' => $customer->id,
            'date' => $now,
            'valid_until' => $now->copy()->addDays(14),
            'tempo_pembayaran' => 21,
            'total_amount' => $totalAmount,
            'status_payment' => 'Belum Bayar',
            'notes' => 'Fixture Playwright sales order from quotation',
            'status' => 'approve',
            'created_by' => $user->id,
            'approve_by' => $user->id,
            'approve_at' => $now,
            'cabang_id' => $cabangId,
            'updated_at' => $now,
        ]);
    } else {
        $quotationId = DB::table('quotations')->insertGetId([
            'quotation_number' => $quotationNumber,
            'customer_id' => $customer->id,
            'date' => $now,
            'valid_until' => $now->copy()->addDays(14),
            'tempo_pembayaran' => 21,
            'total_amount' => $totalAmount,
            'status_payment' => 'Belum Bayar',
            'notes' => 'Fixture Playwright sales order from quotation',
            'status' => 'approve',
            'created_by' => $user->id,
            'approve_by' => $user->id,
            'approve_at' => $now,
            'cabang_id' => $cabangId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    DB::table('quotation_items')->where('quotation_id', $quotationId)->delete();

    DB::table('quotation_items')->insert([
        'quotation_id' => $quotationId,
        'product_id' => $productId,
        'notes' => 'Fixture item',
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'total_price' => $quantity * $unitPrice,
        'discount' => 0,
        'tax' => $tax,
        'tax_type' => 'PPN Excluded',
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    echo "✅ Sale Order from quotation fixture ready\n";
    echo "   Quotation : {$quotationNumber}\n";
    echo "   Product   : PW-SO-QUOTE-001\n";
    echo "   In-stock  : {$warehouseA->kode}\n";
    echo "   Hidden    : {$warehouseB->kode}\n";
});