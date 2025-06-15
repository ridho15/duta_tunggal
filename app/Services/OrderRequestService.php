<?php

namespace App\Services;

use App\Models\PurchaseOrder;

class OrderRequestService
{
    public function approve($orderRequest, $data)
    {
        // Create Purchase order
        $orderRequest->purchaseOrder()->create([
            'po_number' => $data['po_number'],
            'supplier_id' => $data['supplier_id'],
            'order_date' => $data['order_date'],
            'note' => $data['note'],
            'expected_date' => $data['expected_date']
        ]);

        foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
            $orderRequestItem->purchaseOrderItem()->create([
                'purchase_order_id' => $orderRequest->purchaseOrder->id,
                'product_id' => $orderRequestItem->product_id,
                'quantity' => $orderRequestItem->quantity,
                'unit_price' => $orderRequestItem->product->cost_price,
                'discount' => 0,
                'tax' => 0,
            ]);
        }

        $orderRequest->update([
            'status' => 'approved'
        ]);

        return $orderRequest;
    }

    public function reject($orderRequest)
    {
        $orderRequest->update([
            'status' => 'rejected'
        ]);
    }
}
