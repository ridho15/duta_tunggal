<?php

require_once 'vendor/autoload.php';

use App\Models\SaleOrder;
use App\Models\WarehouseConfirmation;

echo "Creating complete test data...\n";

$saleOrder = SaleOrder::with('saleOrderItem.product')->first();
if($saleOrder) {
    // Create main warehouse confirmation
    $wc = WarehouseConfirmation::create([
        'sale_order_id' => $saleOrder->id,
        'status' => 'request'
    ]);

    // Group items by warehouse
    $warehouseGroups = [];
    foreach($saleOrder->saleOrderItem as $item) {
        $whId = $item->warehouse_id;
        if(!isset($warehouseGroups[$whId])) {
            $warehouseGroups[$whId] = [];
        }
        $warehouseGroups[$whId][] = $item;
    }

    // Create warehouse confirmation warehouses
    foreach($warehouseGroups as $whId => $items) {
        $wcw = $wc->warehouseConfirmationWarehouses()->create([
            'warehouse_id' => $whId,
            'status' => 'request'
        ]);

        // Create confirmation items for this warehouse
        foreach($items as $item) {
            $wcw->warehouseConfirmationItems()->create([
                'sale_order_item_id' => $item->id,
                'product_name' => $item->product->name ?? 'Test Product',
                'requested_qty' => $item->quantity,
                'confirmed_qty' => $item->quantity,
                'warehouse_id' => $whId,
                'rak_id' => $item->rak_id,
                'status' => 'confirmed'
            ]);
        }
    }

    echo "Complete test data created! WC ID: {$wc->id}, Warehouses: " . $wc->warehouseConfirmationWarehouses()->count() . "\n";
} else {
    echo "No sale order found\n";
}