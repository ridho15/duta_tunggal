<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\OrderRequestItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;

class OrderRequestService
{
    /**
     * Build the list of OrderRequestItems to convert to PO items.
     * When $data['selected_items'] is present, use only items with include=true
     * and respect the user-edited quantity/price. Otherwise fall back to all items.
     *
     * Returns a Collection of arrays:
     *  ['order_request_item' => OrderRequestItem, 'quantity' => float, 'unit_price' => float]
     */
    private function resolveSelectedItems($orderRequest, array $data, Supplier $supplier): \Illuminate\Support\Collection
    {
        $selectedItems = $data['selected_items'] ?? null;

        if (!empty($selectedItems)) {
            return collect($selectedItems)
                ->filter(fn($row) => !empty($row['include']))
                ->filter(fn($row) => ($row['quantity'] ?? 0) > 0)
                ->map(function ($row) use ($orderRequest, $supplier) {
                    $orderRequestItem = OrderRequestItem::find($row['item_id']);
                    if (!$orderRequestItem || $orderRequestItem->order_request_id !== $orderRequest->id) {
                        return null;
                    }

                    $qty = (float) ($row['quantity'] ?? $orderRequestItem->quantity);

                    // Use form-provided price if given; otherwise fall back to OR item price → supplier pivot → cost_price
                    $formPrice = isset($row['unit_price']) && $row['unit_price'] !== '' ? (float) $row['unit_price'] : null;
                    if ($formPrice !== null && $formPrice >= 0) {
                        $unitPrice = $formPrice;
                    } elseif (($orderRequestItem->unit_price ?? 0) > 0) {
                        $unitPrice = (float) $orderRequestItem->unit_price;
                    } else {
                        $product = $orderRequestItem->product;
                        $sp = $product ? $product->suppliers()->where('suppliers.id', $supplier->id)->first() : null;
                        $unitPrice = $sp ? (float) $sp->pivot->supplier_price : (float) ($product->cost_price ?? 0);
                    }

                    return [
                        'order_request_item' => $orderRequestItem,
                        'quantity'           => $qty,
                        'unit_price'         => $unitPrice,
                        'discount'           => $orderRequestItem->discount ?? 0,
                        'tax'                => $orderRequestItem->tax ?? 0,
                    ];
                })
                ->filter();
        }

        // No selection provided — use all items with remaining quantity
        return $orderRequest->orderRequestItem->map(function ($orderRequestItem) use ($supplier) {
            if (($orderRequestItem->unit_price ?? 0) > 0) {
                $unitPrice = (float) $orderRequestItem->unit_price;
            } else {
                $product = $orderRequestItem->product;
                $sp = $product ? $product->suppliers()->where('suppliers.id', $supplier->id)->first() : null;
                $unitPrice = $sp ? (float) $sp->pivot->supplier_price : (float) ($product->cost_price ?? 0);
            }

            return [
                'order_request_item' => $orderRequestItem,
                'quantity'           => (float) $orderRequestItem->quantity,
                'unit_price'         => $unitPrice,
                'discount'           => $orderRequestItem->discount ?? 0,
                'tax'                => $orderRequestItem->tax ?? 0,
            ];
        });
    }

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
                'po_number'    => $data['po_number'],
                'supplier_id'  => $supplier->id,
                'cabang_id'    => $orderRequest->cabang_id,
                'order_date'   => $data['order_date'],
                'expected_date'=> $data['expected_date'] ?? null,
                'note'         => $data['note'] ?? null,
                'status'       => 'approved',
                'warehouse_id' => $orderRequest->warehouse_id,
                'tempo_hutang' => $supplier->tempo_hutang ?? 0,
                'created_by'   => Auth::id() ?? $orderRequest->created_by,
            ]);

            $resolvedItems = $this->resolveSelectedItems($orderRequest, $data, $supplier);

            foreach ($resolvedItems as $row) {
                /** @var OrderRequestItem $orderRequestItem */
                $orderRequestItem = $row['order_request_item'];

                $orderRequestItem->purchaseOrderItem()->create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id'        => $orderRequestItem->product_id,
                    'quantity'          => $row['quantity'],
                    'unit_price'        => $row['unit_price'],
                    'discount'          => $row['discount'],
                    'tax'               => $row['tax'],
                    'tipe_pajak'        => 'Non Pajak',
                    'currency_id'       => $currency->id,
                ]);

                // Update fulfilled quantity
                $orderRequestItem->fulfilled_quantity = ($orderRequestItem->fulfilled_quantity ?? 0) + $row['quantity'];
                $orderRequestItem->save();
            }
        }

        $orderRequest->update(['status' => 'approved']);

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
            'po_number'    => $data['po_number'],
            'supplier_id'  => $supplier->id,
            'order_date'   => $data['order_date'],
            'expected_date'=> $data['expected_date'] ?? null,
            'note'         => $data['note'] ?? null,
            'status'       => 'approved',
            'warehouse_id' => $orderRequest->warehouse_id,
            'cabang_id'    => $orderRequest->cabang_id,
            'tempo_hutang' => $supplier->tempo_hutang ?? 0,
            'created_by'   => Auth::id() ?? $orderRequest->created_by,
        ]);

        $resolvedItems = $this->resolveSelectedItems($orderRequest, $data, $supplier);

        foreach ($resolvedItems as $row) {
            /** @var OrderRequestItem $orderRequestItem */
            $orderRequestItem = $row['order_request_item'];

            $orderRequestItem->purchaseOrderItem()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id'        => $orderRequestItem->product_id,
                'quantity'          => $row['quantity'],
                'unit_price'        => $row['unit_price'],
                'discount'          => $row['discount'],
                'tax'               => $row['tax'],
                'tipe_pajak'        => 'Non Pajak',
                'currency_id'       => $currency->id,
            ]);

            // Observer will automatically update fulfilled_quantity
        }

        return $purchaseOrder->fresh(['purchaseOrderItem']);
    }

    public function reject($orderRequest)
    {
        $orderRequest->update(['status' => 'rejected']);
    }

    public function submitForApproval($orderRequest)
    {
        $orderRequest->update(['status' => 'pending']);
    }
}

