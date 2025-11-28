<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;

class OrderRequestService
{
    public function approve($orderRequest, $data)
    {
        $createPurchaseOrder = $data['create_purchase_order'] ?? true;

        if ($createPurchaseOrder) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $currency = Currency::query()->first();

            if (! $currency) {
                throw new \RuntimeException('Currency data is required before approving an order request.');
            }

            $purchaseOrder = $orderRequest->purchaseOrder()->create([
                'po_number' => $data['po_number'],
                'supplier_id' => $supplier->id,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'draft',
                'warehouse_id' => $orderRequest->warehouse_id,
                'tempo_hutang' => $supplier->tempo_hutang ?? 0,
                'created_by' => Auth::id() ?? $orderRequest->created_by,
            ]);

            foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
                $orderRequestItem->purchaseOrderItem()->create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $orderRequestItem->product_id,
                    'quantity' => $orderRequestItem->quantity,
                    'unit_price' => $orderRequestItem->product->cost_price,
                    'discount' => 0,
                    'tax' => 0,
                    'tipe_pajak' => 'Non Pajak',
                    'currency_id' => $currency->id,
                ]);
            }
        }

        $orderRequest->update([
            'status' => 'approved'
        ]);

        return $orderRequest->fresh(['purchaseOrder.purchaseOrderItem']);
    }

    public function createPurchaseOrder($orderRequest, $data)
    {
        $supplier = Supplier::findOrFail($data['supplier_id']);
        $currency = Currency::query()->first();

        if (! $currency) {
            throw new \RuntimeException('Currency data is required before creating a purchase order.');
        }

        $purchaseOrder = $orderRequest->purchaseOrder()->create([
            'po_number' => $data['po_number'],
            'supplier_id' => $supplier->id,
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => 'draft',
            'warehouse_id' => $orderRequest->warehouse_id,
            'tempo_hutang' => $supplier->tempo_hutang ?? 0,
            'created_by' => Auth::id() ?? $orderRequest->created_by,
        ]);

        foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
            $orderRequestItem->purchaseOrderItem()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $orderRequestItem->product_id,
                'quantity' => $orderRequestItem->quantity,
                'unit_price' => $orderRequestItem->product->cost_price,
                'discount' => 0,
                'tax' => 0,
                'tipe_pajak' => 'Non Pajak',
                'currency_id' => $currency->id,
            ]);
        }

        return $purchaseOrder;
    }

    public function reject($orderRequest)
    {
        $orderRequest->update([
            'status' => 'rejected'
        ]);
    }
    
    public function submitForApproval($orderRequest)
    {
        $orderRequest->update([
            'status' => 'pending'
        ]);
    }
}
