<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== Checking Delivery Orders available for invoicing ===' . PHP_EOL;

// Get all completed DOs
$completedDOs = \App\Models\DeliveryOrder::where('status', 'completed')->with('deliverySalesOrder.salesOrder')->get();

echo 'Total completed DOs: ' . $completedDOs->count() . PHP_EOL;

if ($completedDOs->count() > 0) {
    foreach ($completedDOs as $do) {
        $so = $do->deliverySalesOrder->first()?->salesOrder;
        $customer = $so ? $so->customer : null;

        echo 'DO: ' . $do->do_number . ' (ID: ' . $do->id . ')' . PHP_EOL;
        echo '  - SO: ' . ($so ? $so->so_number : 'N/A') . PHP_EOL;
        echo '  - Customer: ' . ($customer ? $customer->name : 'N/A') . PHP_EOL;
        echo '  - Status: ' . $do->status . PHP_EOL;

        // Check if already invoiced
        $isInvoiced = \App\Models\Invoice::where('from_model_type', 'App\Models\SaleOrder')
            ->whereNotNull('delivery_orders')
            ->whereJsonContains('delivery_orders', $do->id)
            ->exists();

        echo '  - Already invoiced: ' . ($isInvoiced ? 'YES' : 'NO') . PHP_EOL;
        echo '  - Available for invoice: ' . ($isInvoiced ? 'NO' : 'YES') . PHP_EOL;
        echo PHP_EOL;
    }
} else {
    echo 'No completed DOs found.' . PHP_EOL;
}

// Check SOs that have completed DOs
echo '=== SOs with completed DOs ===' . PHP_EOL;
$sosWithCompletedDOs = \App\Models\SaleOrder::where('status', 'completed')
    ->whereHas('deliverySalesOrder.deliveryOrder', function($q) {
        $q->where('status', 'completed');
    })
    ->with(['customer', 'deliverySalesOrder.deliveryOrder'])
    ->get();

echo 'SOs with completed DOs: ' . $sosWithCompletedDOs->count() . PHP_EOL;

foreach ($sosWithCompletedDOs as $so) {
    echo 'SO: ' . $so->so_number . ' (ID: ' . $so->id . ')' . PHP_EOL;
    echo '  - Customer: ' . $so->customer->name . PHP_EOL;
    echo '  - Tipe Pengiriman: ' . $so->tipe_pengiriman . PHP_EOL;

    $completedDOsForSO = $so->deliverySalesOrder->pluck('deliveryOrder')->filter(function($do) {
        return $do && $do->status === 'completed';
    });

    echo '  - Completed DOs: ' . $completedDOsForSO->count() . PHP_EOL;
    foreach ($completedDOsForSO as $do) {
        echo '    * ' . $do->do_number . ' (ID: ' . $do->id . ')' . PHP_EOL;
    }
    echo PHP_EOL;
}