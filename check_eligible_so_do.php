<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== Sale Orders with status completed and not Ambil Sendiri ===' . PHP_EOL;
$eligibleSOs = \App\Models\SaleOrder::where('status', 'completed')
    ->where('tipe_pengiriman', '!=', 'Ambil Sendiri')
    ->with(['customer', 'deliverySalesOrder.deliveryOrder'])
    ->get();

echo 'Eligible SOs: ' . $eligibleSOs->count() . PHP_EOL;

foreach ($eligibleSOs as $so) {
    echo 'SO: ' . $so->so_number . ' (ID: ' . $so->id . ')' . PHP_EOL;
    echo '  - Customer: ' . $so->customer->name . PHP_EOL;
    echo '  - Tipe Pengiriman: ' . $so->tipe_pengiriman . PHP_EOL;

    $dos = $so->deliverySalesOrder->pluck('deliveryOrder')->filter(function($do) {
        return $do && in_array($do->status, ['sent', 'completed']);
    });

    echo '  - Eligible DOs (sent/completed): ' . $dos->count() . PHP_EOL;
    foreach ($dos as $do) {
        $isInvoiced = \App\Models\Invoice::where('from_model_type', 'App\Models\SaleOrder')
            ->whereNotNull('delivery_orders')
            ->whereJsonContains('delivery_orders', $do->id)
            ->exists();
        echo '    * ' . $do->do_number . ' (ID: ' . $do->id . ') - Status: ' . $do->status . ' - Invoiced: ' . ($isInvoiced ? 'YES' : 'NO') . PHP_EOL;
    }
    echo PHP_EOL;
}

echo '=== Summary ===' . PHP_EOL;
echo 'Total eligible SOs: ' . $eligibleSOs->count() . PHP_EOL;
$totalEligibleDOs = 0;
foreach ($eligibleSOs as $so) {
    $dos = $so->deliverySalesOrder->pluck('deliveryOrder')->filter(function($do) {
        return $do && in_array($do->status, ['sent', 'completed']);
    });
    $totalEligibleDOs += $dos->count();
}
echo 'Total eligible DOs (sent/completed): ' . $totalEligibleDOs . PHP_EOL;