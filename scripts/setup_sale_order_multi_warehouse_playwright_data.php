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
        throw new RuntimeException('No users found for multi-warehouse fixture setup');
    }

    $cabangId = $user->cabang_id ?? DB::table('cabangs')->value('id');
    if (! $cabangId) {
        throw new RuntimeException('No cabang found for multi-warehouse fixture setup');
    }

    $customer = DB::table('customers')->where('cabang_id', $cabangId)->first()
        ?? DB::table('customers')->orderBy('id')->first();
    if (! $customer) {
        throw new RuntimeException('No customer found for multi-warehouse fixture setup');
    }

    $warehouses = DB::table('warehouses')->where('cabang_id', $cabangId)->orderBy('id')->limit(2)->get();
    if ($warehouses->count() < 2) {
        $warehouses = DB::table('warehouses')->orderBy('id')->limit(2)->get();
    }
    if ($warehouses->count() < 2) {
        throw new RuntimeException('Need at least two warehouses for multi-warehouse fixture setup');
    }

    $product = DB::table('products')->orderBy('id')->first();
    if (! $product) {
        throw new RuntimeException('No product found for multi-warehouse fixture setup');
    }

    $soNumber = 'SO-TEST-MW-0001';
    $existingId = DB::table('sale_orders')->where('so_number', $soNumber)->value('id');

    if ($existingId) {
        DB::table('sale_order_item_warehouse_allocations')
            ->whereIn('sale_order_item_id', DB::table('sale_order_items')->where('sale_order_id', $existingId)->pluck('id')->toArray())
            ->delete();
        DB::table('sale_order_items')->where('sale_order_id', $existingId)->delete();
        DB::table('sale_orders')->where('id', $existingId)->delete();
    }

    $qty = 10;
    $unitPrice = 100000;
    $discount = 0;
    $tax = 11;
    $totalAmount = $qty * $unitPrice * 1.11;

    $saleOrderId = DB::table('sale_orders')->insertGetId([
        'customer_id' => $customer->id,
        'quotation_id' => null,
        'so_number' => $soNumber,
        'order_date' => $now->toDateTimeString(),
        'status' => 'draft',
        'delivery_date' => $now->addDays(2)->toDateTimeString(),
        'tempo_pembayaran' => 30,
        'total_amount' => $totalAmount,
        'created_by' => $user->id,
        'shipped_to' => $customer->address ?? 'Alamat fixture multi-warehouse',
        'tipe_pengiriman' => 'Kirim Langsung',
        'cabang_id' => $cabangId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $saleOrderItemId = DB::table('sale_order_items')->insertGetId([
        'sale_order_id' => $saleOrderId,
        'product_id' => $product->id,
        'quantity' => $qty,
        'delivered_quantity' => 0,
        'unit_price' => $unitPrice,
        'discount' => $discount,
        'tax' => $tax,
        'tipe_pajak' => 'PPN Excluded',
        'warehouse_id' => null,
        'rak_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    DB::table('sale_order_item_warehouse_allocations')->insert([
        [
            'sale_order_item_id' => $saleOrderItemId,
            'warehouse_id' => $warehouses[0]->id,
            'quantity' => 5,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ],
        [
            'sale_order_item_id' => $saleOrderItemId,
            'warehouse_id' => $warehouses[1]->id,
            'quantity' => 5,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ],
    ]);

    echo "✅ Multi-warehouse SaleOrder fixture ready\n";
    echo "   SO Number: {$soNumber}\n";
    echo "   Total    : Rp " . number_format((float) $totalAmount, 0, ',', '.') . "\n";
});
