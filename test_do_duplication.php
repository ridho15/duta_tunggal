<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$product = \App\Models\Product::first();
$warehouse = \App\Models\Warehouse::first();
$customer = \App\Models\Customer::first();

if ($product && $warehouse && $customer) {
    echo "Testing potential duplication in DO complete process\n";
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

    echo "Initial inventory: available={$inventory->qty_available}, reserved={$inventory->qty_reserved}\n";

    // Create SO
    $so = \App\Models\SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'confirmed',
    ]);

    $soi = \App\Models\SaleOrderItem::factory()->create([
        'sale_order_id' => $so->id,
        'product_id' => $product->id,
        'quantity' => 20,
        'unit_price' => 1000,
    ]);

    echo "SO created: {$so->so_number} (ID: {$so->id})\n";

    // Create DO
    $do = \App\Models\DeliveryOrder::factory()->create([
        'status' => 'approved', // Start with approved
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

    echo "DO created: {$do->do_number} (ID: {$do->id}), status: {$do->status}\n";

    // Simulate the Filament action: updateStatus + postDeliveryOrder
    echo "\n--- Simulating Filament 'completed' action ---\n";

    // Step 1: updateStatus (changes status to 'completed', triggers observer)
    echo "Step 1: Calling updateStatus to 'completed'\n";
    $service = app(\App\Services\DeliveryOrderService::class);
    $service->updateStatus($do, 'completed');
    $do->refresh();
    echo "DO status after updateStatus: {$do->status}\n";

    // Check stock movements after observer
    $movementsAfterObserver = \App\Models\StockMovement::where('from_model_id', $doi->id)->get();
    echo "Stock movements after observer: {$movementsAfterObserver->count()}\n";
    foreach ($movementsAfterObserver as $m) {
        echo "  ID: {$m->id}, Type: {$m->type}, Qty: {$m->quantity}\n";
    }

    // Step 2: postDeliveryOrder
    echo "\nStep 2: Calling postDeliveryOrder\n";
    $result = $service->postDeliveryOrder($do);
    echo "Post result: " . json_encode($result) . "\n";

    // Check stock movements after postDeliveryOrder
    $movementsAfterPost = \App\Models\StockMovement::where('from_model_id', $doi->id)->get();
    echo "Stock movements after postDeliveryOrder: {$movementsAfterPost->count()}\n";
    foreach ($movementsAfterPost as $m) {
        echo "  ID: {$m->id}, Type: {$m->type}, Qty: {$m->quantity}\n";
    }

    // Check inventory after
    $inventory->refresh();
    echo "Final inventory: available={$inventory->qty_available}, reserved={$inventory->qty_reserved}\n";

    // Check if duplicated
    if ($movementsAfterPost->count() > 1) {
        echo "WARNING: Potential duplication detected!\n";
    } else {
        echo "No duplication detected.\n";
    }

} else {
    echo "No product, warehouse, or customer found\n";
}