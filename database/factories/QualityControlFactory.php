<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QualityControl>
 */
class QualityControlFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qc_number' => 'QC-' . strtoupper($this->faker->unique()->randomLetter() . $this->faker->unique()->randomLetter() . $this->faker->numberBetween(1000, 9999)),
            'inspected_by' => 1, // will be overridden
            'passed_quantity' => $this->faker->numberBetween(1, 100),
            'rejected_quantity' => $this->faker->numberBetween(0, 10),
            'notes' => $this->faker->optional()->sentence(),
            'status' => $this->faker->boolean(),
            'warehouse_id' => 1, // will be overridden
            'reason_reject' => $this->faker->optional()->sentence(),
            'product_id' => 1, // will be overridden
            'date_send_stock' => $this->faker->optional()->dateTime(),
            'rak_id' => $this->faker->optional()->numberBetween(1, 10),
            'from_model_id' => 1, // will be overridden
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            // DB column is a nullable timestamp; use null as default to avoid invalid datetime insertion
            'purchase_return_processed' => null,
        ];
    }
}
