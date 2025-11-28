<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fromWarehouse = Warehouse::factory()->create();
        $toWarehouse = Warehouse::factory()->create();

        return [
            'transfer_number' => 'TN-' . now()->format('Ymd') . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'status' => $this->faker->randomElement(['Draft', 'Request', 'Approved', 'Reject', 'Cancelled']),
        ];
    }
}