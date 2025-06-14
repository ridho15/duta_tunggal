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
        $deliveryOrder = DeliveryOrder::has('deliverySalesOrder')->inRandomOrder()->first();
        $saleOrderItem = SaleOrderItem::whereHas('saleOrder', function ($query) use ($deliveryOrder) {
            $query->whereHas('deliverySalesOrder', function ($query) use ($deliveryOrder) {
                $query->where('delivery_order_id', $deliveryOrder->id);
            });
        })->inRandomOrder()->first();
        $product_id = $saleOrderItem->product_id;
        $quantity = $saleOrderItem->quantity;
        return [
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $product_id,
            'quantity' => $quantity,
            'reason' => $this->faker->sentence()
        ];
    }
}
