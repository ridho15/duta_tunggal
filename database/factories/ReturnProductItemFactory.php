<?php

namespace Database\Factories;

use App\Models\DeliveryOrderItem;
use App\Models\ReturnProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReturnProductItem>
 */
class ReturnProductItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $returnProduct = ReturnProduct::has('warehouse.rak')->inRandomOrder()->first();

        // Find a valid delivery order item
        $deliveryOrderItem = null;
        if ($returnProduct && $returnProduct->fromModel) {
            $deliveryOrderItem = $returnProduct->fromModel->deliveryOrderItem()->where('quantity', '>', 1)->inRandomOrder()->first();
        }

        // If no valid delivery order item found, create a fallback
        if (!$deliveryOrderItem) {
            // Find any delivery order item or create one
            $deliveryOrderItem = DeliveryOrderItem::where('quantity', '>', 1)->inRandomOrder()->first();

            if (!$deliveryOrderItem) {
                // Create a delivery order item if none exists
                $deliveryOrder = \App\Models\DeliveryOrder::factory()->create();
                $deliveryOrderItem = \App\Models\DeliveryOrderItem::factory()->create([
                    'delivery_order_id' => $deliveryOrder->id,
                    // Don't specify quantity here - let DeliveryOrderItemFactory calculate it
                ]);
            }
        }

        $rakId = $returnProduct->warehouse->rak()->inRandomOrder()->first()->id;

        return [
            'return_product_id' => $returnProduct->id,
            'from_item_model_id' => $deliveryOrderItem->id,
            'product_id' => $deliveryOrderItem->product_id,
            'from_item_model_type' => 'App\Models\DeliveryOrderItem',
            'quantity' => random_int(1, $deliveryOrderItem->quantity),
            'rak_id' => $rakId,
            'condition' => Arr::random(['good', 'damage', 'repair']),
            'note' => $this->faker->sentence()
        ];
    }
}
