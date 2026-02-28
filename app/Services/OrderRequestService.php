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
                'cabang_id' => $orderRequest->cabang_id,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'approved', // Auto-approved since OR is already approved
                'warehouse_id' => $orderRequest->warehouse_id,
                'tempo_hutang' => $supplier->tempo_hutang ?? 0,
                'created_by' => Auth::id() ?? $orderRequest->created_by,
            ]);

            foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
                // Use unit_price from order request item (should already be supplier price);
                // if not available, try supplier price from pivot, then fallback to cost_price
                if (($orderRequestItem->unit_price ?? 0) > 0) {
                    $unitPrice = $orderRequestItem->unit_price;
                } else {
                    $product = $orderRequestItem->product;
                    $supplierProduct = $product ? $product->suppliers()->where('suppliers.id', $supplier->id)->first() : null;
                    $unitPrice = $supplierProduct ? (float) $supplierProduct->pivot->supplier_price : ($product->cost_price ?? 0);
                }
                $discount = ($orderRequestItem->discount ?? 0);
                $tax = ($orderRequestItem->tax ?? 0);
                
                $orderRequestItem->purchaseOrderItem()->create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $orderRequestItem->product_id,
                    'quantity' => $orderRequestItem->quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'tax' => $tax,
                    'tipe_pajak' => 'Non Pajak',
                    'currency_id' => $currency->id,
                ]);
                
                // Update fulfilled quantity for the order request item
                $orderRequestItem->fulfilled_quantity = ($orderRequestItem->fulfilled_quantity ?? 0) + $orderRequestItem->quantity;
                $orderRequestItem->save();
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
            'status' => 'approved', // Auto-approved since OR is already approved
            'warehouse_id' => $orderRequest->warehouse_id,
            'tempo_hutang' => $supplier->tempo_hutang ?? 0,
            'created_by' => Auth::id() ?? $orderRequest->created_by,
        ]);

        foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
            $unitPrice = (($orderRequestItem->unit_price ?? 0) > 0) ? $orderRequestItem->unit_price : ($orderRequestItem->product->cost_price ?? 0);
            $discount = ($orderRequestItem->discount ?? 0);
            $tax = ($orderRequestItem->tax ?? 0);
            
            $orderRequestItem->purchaseOrderItem()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $orderRequestItem->product_id,
                'quantity' => $orderRequestItem->quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax' => $tax,
                'tipe_pajak' => 'Non Pajak',
                'currency_id' => $currency->id,
            ]);
            
            // Observer will automatically update fulfilled_quantity
        }

        return $purchaseOrder->fresh(['purchaseOrderItem']);
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
