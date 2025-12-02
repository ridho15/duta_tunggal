<?php

namespace App\Services;

use App\Models\SaleOrderItem;
use Illuminate\Validation\ValidationException;

class DeliveryOrderItemService
{
    /**
     * Validate delivery order items against the selected sales order.
     *
     * @param  int   $salesOrderId
     * @param  array $items Form data array from Filament repeater
     * @param  int|null $currentDeliveryOrderId When editing, exclude current DO from allocation check
     * @throws ValidationException
     */
    public function validateItemsForSalesOrder(int $salesOrderId, array $items, ?int $currentDeliveryOrderId = null): void
    {
        if (empty($items)) {
            return;
        }

        $this->assertNoDuplicateSaleOrderItems($items);

        $saleOrderItems = SaleOrderItem::query()
            ->where('sale_order_id', $salesOrderId)
            ->with('product')
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $saleOrderItemId = $item['sale_order_item_id'] ?? null;
            $quantity = (float) ($item['quantity'] ?? 0);

            if (!$saleOrderItemId) {
                throw ValidationException::withMessages([
                    "deliveryOrderItem.{$index}.sale_order_item_id" => 'Item sales order wajib dipilih.',
                ]);
            }

            /** @var SaleOrderItem|null $saleOrderItem */
            $saleOrderItem = $saleOrderItems->get($saleOrderItemId);

            if (!$saleOrderItem) {
                throw ValidationException::withMessages([
                    "deliveryOrderItem.{$index}.sale_order_item_id" => 'Item sales order tidak ditemukan atau tidak terkait dengan pesanan ini.',
                ]);
            }

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "deliveryOrderItem.{$index}.quantity" => 'Quantity harus lebih besar dari 0.',
                ]);
            }

            if ($quantity > $saleOrderItem->quantity) {
                $productName = $saleOrderItem->product->name ?? 'produk';

                throw ValidationException::withMessages([
                    "deliveryOrderItem.{$index}.quantity" => "Quantity untuk {$productName} ({$quantity}) melebihi quantity pada sales order ({$saleOrderItem->quantity}).",
                ]);
            }

            $available = $this->calculateAvailableQuantity($saleOrderItem, $currentDeliveryOrderId);

            // Note: Validasi remaining quantity dihapus - tetap bisa disimpan meskipun melebihi sisa quantity
            // if ($quantity > $available) {
            //     $productName = $saleOrderItem->product->name ?? 'produk';
            //     throw ValidationException::withMessages([
            //         "deliveryOrderItem.{$index}.quantity" => "Quantity untuk {$productName} ({$quantity}) melebihi sisa quantity yang tersedia ({$available}).",
            //     ]);
            // }
        }
    }

    /**
     * Determine remaining quantity that can still be allocated for a sales order item.
     */
    public function calculateAvailableQuantity(SaleOrderItem $saleOrderItem, ?int $currentDeliveryOrderId = null): float
    {
        $committed = $this->calculateCommittedQuantity($saleOrderItem, $currentDeliveryOrderId);

        return max(0, (float) $saleOrderItem->quantity - $committed);
    }

    /**
     * Sum already allocated quantities for the sales order item, excluding cancelled/rejected DOs.
     */
    protected function calculateCommittedQuantity(SaleOrderItem $saleOrderItem, ?int $currentDeliveryOrderId = null): float
    {
        return (float) $saleOrderItem->deliveryOrderItems()
            ->whereHas('deliveryOrder', function ($query) use ($currentDeliveryOrderId) {
                $query->whereNotIn('status', ['cancelled', 'rejected']);

                if ($currentDeliveryOrderId !== null) {
                    $query->where('id', '!=', $currentDeliveryOrderId);
                }
            })
            ->sum('quantity');
    }

    /**
     * Ensure we do not duplicate sales order items within a single delivery order.
     */
    protected function assertNoDuplicateSaleOrderItems(array $items): void
    {
        $ids = collect($items)
            ->pluck('sale_order_item_id')
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $duplicates = $ids->duplicates();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'deliveryOrderItem' => 'Tidak boleh ada item sales order yang duplikat dalam satu delivery order.',
            ]);
        }
    }
}