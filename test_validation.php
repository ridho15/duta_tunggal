<?php

echo 'Testing validation with normalized salesOrderIds...' . PHP_EOL;

// Simulate normalized salesOrderIds
$salesOrderIds = [1]; // Integer array

if (!empty($salesOrderIds)) {
    foreach ($salesOrderIds as $salesOrderId) {
        $salesOrder = \App\Models\SaleOrder::find($salesOrderId);
        if ($salesOrder) {
            echo '✓ Sales Order ' . $salesOrder->so_number . ' found' . PHP_EOL;
            echo '  Status: ' . $salesOrder->status . PHP_EOL;
            echo '  Tipe Pengiriman: ' . $salesOrder->tipe_pengiriman . PHP_EOL;
            echo '  Warehouse Confirmed At: ' . $salesOrder->warehouse_confirmed_at . PHP_EOL;

            // Check validation conditions
            if ($salesOrder->status !== 'confirmed') {
                echo '✗ Status not confirmed' . PHP_EOL;
            } else {
                echo '✓ Status is confirmed' . PHP_EOL;
            }

            if ($salesOrder->tipe_pengiriman !== 'Kirim Langsung') {
                echo '✗ Tipe pengiriman not Kirim Langsung' . PHP_EOL;
            } else {
                echo '✓ Tipe pengiriman is Kirim Langsung' . PHP_EOL;
            }

            if (!$salesOrder->warehouse_confirmed_at) {
                echo '✗ Warehouse not confirmed' . PHP_EOL;
            } else {
                echo '✓ Warehouse is confirmed' . PHP_EOL;
            }
        } else {
            echo '✗ Sales Order with ID ' . $salesOrderId . ' not found' . PHP_EOL;
        }
    }
} else {
    echo '✗ salesOrderIds is empty' . PHP_EOL;
}