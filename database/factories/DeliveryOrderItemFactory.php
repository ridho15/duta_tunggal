<?php

namespace Database\Factories;

use App\Models\DeliveryOrder;
use App\Models\SaleOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryOrderItem>
 */
class DeliveryOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Try to find a delivery order with deliverySalesOrder relationship
        $deliveryOrder = DeliveryOrder::has('deliverySalesOrder')->inRandomOrder()->first();

        // If no delivery order with the relationship exists, create fallback data
        if (!$deliveryOrder) {
            // Find any delivery order
            $deliveryOrder = DeliveryOrder::inRandomOrder()->first();

            if (!$deliveryOrder) {
                // Create a delivery order if none exists
                $deliveryOrder = DeliveryOrder::factory()->create();
            }
        }

        // Try to find sale order item with the complex relationship
        $saleOrderItem = null;
        if ($deliveryOrder) {
            $saleOrderItem = SaleOrderItem::whereHas('saleOrder', function ($query) use ($deliveryOrder) {
                $query->whereHas('deliverySalesOrder', function ($query) use ($deliveryOrder) {
                    $query->where('delivery_order_id', $deliveryOrder->id);
                });
            })->inRandomOrder()->first();
        }

        // If no sale order item found with complex relationship, find any sale order item
        if (!$saleOrderItem) {
            $saleOrderItem = SaleOrderItem::inRandomOrder()->first();

            if (!$saleOrderItem) {
                // Create a sale order item if none exists
                $saleOrder = \App\Models\SaleOrder::factory()->create();
                $product = \App\Models\Product::inRandomOrder()->first() ?: \App\Models\Product::factory()->create();
                $saleOrderItem = \App\Models\SaleOrderItem::factory()->create([
                    'sale_order_id' => $saleOrder->id,
                    'product_id' => $product->id,
                    'quantity' => $this->faker->numberBetween(1, 10),
                ]);
            }
        }

        // Calculate remaining quantity available for delivery
        $alreadyDeliveredQty = $saleOrderItem->deliveryOrderItems()
            ->whereHas('deliveryOrder', function ($query) {
                $query->whereNotIn('status', ['cancelled', 'rejected']);
            })
            ->sum('quantity');

        $remainingQty = max(0, $saleOrderItem->quantity - $alreadyDeliveredQty);
        $deliveryQty = $remainingQty > 0 ? $this->faker->numberBetween(1, $remainingQty) : 1;

        return [
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $saleOrderItem->product_id,
            'quantity' => $deliveryQty,
            'reason' => $this->faker->sentence()
        ];
    }
}
