<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$product = \App\Models\Product::first();
$warehouse = \App\Models\Warehouse::first();
$customer = \App\Models\Customer::first();

if ($product && $warehouse && $customer) {
    echo "Product: {$product->name} (ID: {$product->id})\n";
    echo "Warehouse: {$warehouse->name} (ID: {$warehouse->id})\n";
    echo "Customer: {$customer->name} (ID: {$customer->id})\n";

    // Create inventory stock
    $inventory = \App\Models\InventoryStock::updateOrCreate([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
    ], [
        'qty_available' => 100,
        'qty_reserved' => 0,
        'rak_id' => 1,
    ]);

    echo "Inventory created/updated: available={$inventory->qty_available}, reserved={$inventory->qty_reserved}\n";

    // Create SO
    $so = \App\Models\SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'confirmed',
    ]);

    $soi = \App\Models\SaleOrderItem::factory()->create([
        'sale_order_id' => $so->id,
        'product_id' => $product->id,
        'quantity' => 20, // More than DO quantity
        'unit_price' => 1000,
    ]);

    echo "SO created: {$so->so_number} (ID: {$so->id})\n";
    echo "SOI created: quantity={$soi->quantity} (ID: {$soi->id})\n";

    // Create DO
    $do = \App\Models\DeliveryOrder::factory()->create([
        'status' => 'approved',
        'warehouse_id' => $warehouse->id,
    ]);

    $doi = \App\Models\DeliveryOrderItem::factory()->create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soi->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    // Link DO to SO
    DB::table('delivery_sales_orders')->insert([
        'delivery_order_id' => $do->id,
        'sales_order_id' => $so->id,
    ]);

    echo "DO created: {$do->do_number} (ID: {$do->id})\n";
    echo "DOI created: quantity={$doi->quantity} (ID: {$doi->id})\n";

    // Post DO
    $service = app(\App\Services\DeliveryOrderService::class);
    $result = $service->postDeliveryOrder($do);
    echo "Post result: " . json_encode($result) . "\n";

    // Check stock movements
    $movements = \App\Models\StockMovement::where('from_model_id', $doi->id)->get();
    echo "Stock movements created: {$movements->count()}\n";
    foreach ($movements as $m) {
        echo "ID: {$m->id}, Type: {$m->type}, Quantity: {$m->quantity}, Product ID: {$m->product_id}, Warehouse ID: {$m->warehouse_id}, Date: {$m->date}, Notes: {$m->notes}\n";
    }

    // Check inventory after
    $inventory->refresh();
    echo "Inventory after: available={$inventory->qty_available}, reserved={$inventory->qty_reserved}\n";

} else {
    echo "No product, warehouse, or customer found\n";
}