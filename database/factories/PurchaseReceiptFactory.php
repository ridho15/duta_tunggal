<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseReceipt>
 */
class PurchaseReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Create a PurchaseOrder with a Supplier to ensure cabang consistency
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
        ]);

        return [
            'receipt_number' => 'RN-' . strtoupper(Str::random(6)),
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => 1,
            'notes' => $this->faker->optional()->sentence(),
            'currency_id' => 1,
            'other_cost' => $this->faker->numberBetween(0, 10000),
            'status' => 'completed',
            'cabang_id' => $supplier->cabang_id,
        ];
    }
}
