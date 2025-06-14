<?php

namespace Database\Factories;

use App\Models\DeliveryOrder;
use App\Models\SaleOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliverySalesOrder>
 */
class DeliverySalesOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_order_id' => SaleOrder::inRandomOrder()->first()->id,
            'delivery_order_id' => DeliveryOrder::inRandomOrder()->first()->id,
        ];
    }
}
