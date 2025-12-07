<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TESTING JOURNAL ENTRY NOTIFICATIONS ===' . PHP_EOL;

// Create test stock opname
$warehouse = \App\Models\Warehouse::first();
if (!$warehouse) {
    echo 'No warehouse found, creating test warehouse...' . PHP_EOL;
    $warehouse = \App\Models\Warehouse::create([
        'name' => 'Test Warehouse',
        'code' => 'TEST',
        'cabang_id' => 1
    ]);
}

$stockOpname = \App\Models\StockOpname::create([
    'opname_number' => 'OPN-' . now()->format('YmdHis') . '-001',
    'opname_date' => now(),
    'warehouse_id' => $warehouse->id,
    'status' => 'draft',
    'created_by' => 1
]);

echo 'Created test Stock Opname: ' . $stockOpname->opname_number . PHP_EOL;

// Create test item
$product = \App\Models\Product::first();
if (!$product) {
    echo 'No product found, creating test product...' . PHP_EOL;
    $product = \App\Models\Product::create([
        'name' => 'Test Product',
        'code' => 'TEST001',
        'unit' => 'pcs'
    ]);
}

\App\Models\StockOpnameItem::create([
    'stock_opname_id' => $stockOpname->id,
    'product_id' => $product->id,
    'system_quantity' => 10,
    'physical_quantity' => 15,
    'difference_quantity' => 5,
    'difference_value' => 50000,
    'unit_cost' => 10000
]);

echo 'Created test item with positive adjustment' . PHP_EOL;

// Check notifications before approval
$user = \App\Models\User::find(1);
$notificationsBefore = $user->notifications()->count();
echo 'Notifications before approval: ' . $notificationsBefore . PHP_EOL;

// Approve stock opname to create journal entries
$stockOpname->update(['status' => 'approved']);
echo 'Stock opname approved, journal entries created.' . PHP_EOL;

// Check notifications after approval
$notificationsAfter = $user->notifications()->count();
echo 'Notifications after approval: ' . $notificationsAfter . PHP_EOL;

$newNotifications = $notificationsAfter - $notificationsBefore;
echo 'New notifications created: ' . $newNotifications . PHP_EOL;

// Check the latest notifications
$latestNotifications = $user->notifications()->latest()->take(3)->get();
echo PHP_EOL . 'Latest notifications:' . PHP_EOL;
foreach ($latestNotifications as $notification) {
    echo '  - Type: ' . $notification->type . PHP_EOL;
    echo '  - Data: ' . json_encode($notification->data) . PHP_EOL;
    echo '  - Created: ' . $notification->created_at . PHP_EOL;
}

// Check journal entries created
$journalEntries = $stockOpname->journalEntries()->where('journal_type', 'stock_opname')->get();
echo PHP_EOL . 'Journal entries created: ' . $journalEntries->count() . PHP_EOL;

// Clean up
$stockOpname->delete();
echo PHP_EOL . '✅ Test completed and cleaned up!' . PHP_EOL;