<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TESTING JOURNAL CREATION WHEN PO ITEM IS CREATED ===' . PHP_EOL;

// Find a PO without items for clean testing
$po = \App\Models\PurchaseOrder::doesntHave('purchaseOrderItem')->first();
if (!$po) {
    echo 'Creating a test PO...' . PHP_EOL;
    $po = \App\Models\PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-TEST-JOURNAL-' . now()->format('YmdHis'),
        'order_date' => now(),
        'status' => 'draft',
        'total_amount' => 0,
        'cabang_id' => 1
    ]);
    echo 'Created test PO: ' . $po->po_number . PHP_EOL;
}

$initialEntries = $po->journalEntries()->count();
echo 'PO ' . $po->po_number . ' initially has ' . $initialEntries . ' journal entries' . PHP_EOL;

// Create a PO item
echo 'Creating PO item...' . PHP_EOL;
try {
    $item = \App\Models\PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => 1,
        'quantity' => 5,
        'unit_price' => 10000,
        'discount' => 0,
        'tax' => 0,
        'subtotal' => 50000,
        'currency_id' => 1
    ]);

    echo 'Created PO item ID: ' . $item->id . PHP_EOL;

    // Check if journal entries were created
    $po->refresh();
    $afterEntries = $po->journalEntries()->count();
    echo 'PO now has ' . $afterEntries . ' journal entries' . PHP_EOL;

    if ($afterEntries > $initialEntries) {
        echo '✅ Journal entries WERE created when PO item was created!' . PHP_EOL;
        $newEntries = $po->journalEntries()->get();
        foreach ($newEntries as $entry) {
            echo '  - Entry ID: ' . $entry->id . ', Reference: ' . $entry->reference . PHP_EOL;
        }
    } else {
        echo '❌ No new journal entries were created when PO item was created' . PHP_EOL;
    }

    // Clean up
    $item->delete();
} catch (Exception $e) {
    echo 'Error creating PO item: ' . $e->getMessage() . PHP_EOL;
}

if (strpos($po->po_number, 'TEST-JOURNAL') !== false) {
    $po->delete();
}

echo PHP_EOL . 'Test completed.' . PHP_EOL;