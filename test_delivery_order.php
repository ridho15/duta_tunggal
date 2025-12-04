<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
auth()->login($user);

// Use existing data
$warehouse = \App\Models\Warehouse::first();
$customer = \App\Models\Customer::first();
$driver = \App\Models\Driver::first();
$vehicle = \App\Models\Vehicle::first();
$product = \App\Models\Product::first();

// Create initial inventory if not exists
$inventoryStock = \App\Models\InventoryStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
if (!$inventoryStock) {
    $inventoryStock = \App\Models\InventoryStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty_available' => 20,
        'qty_reserved' => 0,
    ]);
}

// Create sales order
$saleOrder = \App\Models\SaleOrder::create([
    'customer_id' => $customer->id,
    'so_number' => 'SO-' . now()->format('Ymd') . '-TEST3',
    'order_date' => now(),
    'status' => 'draft',
    'delivery_date' => now()->addDays(1),
    'total_amount' => 750000,
    'tipe_pengiriman' => 'Kirim Langsung',
    'created_by' => $user->id,
]);

$saleOrderItem = \App\Models\SaleOrderItem::create([
    'sale_order_id' => $saleOrder->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 75000,
    'discount' => 0,
    'tax' => 0,
    'warehouse_id' => $warehouse->id,
]);

// Approve sales order
$saleOrder->update(['status' => 'approved']);
$saleOrder->fresh()->warehouseConfirmation->update(['status' => 'Confirmed']);

// Create delivery order
$deliveryOrder = \App\Models\DeliveryOrder::create([
    'do_number' => 'DO-' . now()->format('Ymd') . '-TEST3',
    'delivery_date' => now(),
    'warehouse_id' => $warehouse->id,
    'driver_id' => $driver->id,
    'vehicle_id' => $vehicle->id,
    'status' => 'draft',
]);

\App\Models\DeliveryOrderItem::create([
    'delivery_order_id' => $deliveryOrder->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'warehouse_id' => $warehouse->id,
    'sale_order_item_id' => $saleOrderItem->id,
]);

// Link delivery order to sales order
$deliveryOrder->salesOrders()->attach($saleOrder->id);

echo 'Test data created successfully - DO: ' . $deliveryOrder->do_number . PHP_EOL;