<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $now = now();

    $user = DB::table('users')->where('email', 'ralamzah@gmail.com')->first()
        ?? DB::table('users')->orderBy('id')->first();
    if (!$user) {
        throw new RuntimeException('No users found for G2 fixture setup');
    }

    $cabangId = $user->cabang_id ?? DB::table('cabangs')->value('id');
    $customer = DB::table('customers')->where('cabang_id', $cabangId)->first()
        ?? DB::table('customers')->orderBy('id')->first();
    if (!$customer) {
        throw new RuntimeException('No customers found for G2 fixture setup');
    }

    $warehouse = DB::table('warehouses')->where('cabang_id', $cabangId)->first()
        ?? DB::table('warehouses')->orderBy('id')->first();
    if (!$warehouse) {
        throw new RuntimeException('No warehouses found for G2 fixture setup');
    }

    $product = DB::table('products')->orderBy('id')->first();
    if (!$product) {
        throw new RuntimeException('No products found for G2 fixture setup');
    }

    $soNumber = 'SO-TEST-G2-0001';
    $existingId = DB::table('sale_orders')->where('so_number', $soNumber)->value('id');

    if ($existingId) {
        DB::table('sale_order_items')->where('sale_order_id', $existingId)->delete();
        DB::table('sale_orders')->where('id', $existingId)->delete();
    }

    $qty = 4;
    $unitPrice = 125000;
    $discount = 0;
    $tax = 11;
    $subtotal = $qty * $unitPrice * 1.11;

    $saleOrderId = DB::table('sale_orders')->insertGetId([
        'customer_id' => $customer->id,
        'quotation_id' => null,
        'so_number' => $soNumber,
        'order_date' => now()->toDateTimeString(),
        'status' => 'draft',
        'delivery_date' => now()->addDays(2)->toDateTimeString(),
        'tempo_pembayaran' => 30,
        'total_amount' => $subtotal,
        'created_by' => $user->id,
        'shipped_to' => $customer->address ?? 'Alamat fixture G2',
        'tipe_pengiriman' => 'Kirim Langsung',
        'cabang_id' => $cabangId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('sale_order_items')->insert([
        'sale_order_id' => $saleOrderId,
        'product_id' => $product->id,
        'quantity' => $qty,
        'delivered_quantity' => 0,
        'unit_price' => $unitPrice,
        'discount' => $discount,
        'tax' => $tax,
        'tipe_pajak' => 'Exclusive',
        'warehouse_id' => $warehouse->id,
        'rak_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    echo "✅ G2 SaleOrder fixture ready\n";
    echo "   SO Number: {$soNumber}\n";
    echo "   Total    : Rp " . number_format((float) $subtotal, 0, ',', '.') . "\n";
});
