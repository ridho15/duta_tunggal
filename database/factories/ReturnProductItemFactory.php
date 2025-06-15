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
        $deliveryOrderItem = $returnProduct->fromModel->deliveryOrderItem()->where('quantity', '>', 1)->inRandomOrder()->first();
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
