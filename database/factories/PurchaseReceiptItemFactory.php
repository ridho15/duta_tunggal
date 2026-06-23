<?php

namespace Database\Factories;

use App\Models\PurchaseReceiptItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseReceiptItem>
 */
class PurchaseReceiptItemFactory extends Factory
{
    protected $model = PurchaseReceiptItem::class;

    public function configure(): static
    {
        return $this->afterMaking(function (PurchaseReceiptItem $item) {
            $qtyAccepted = (float) ($item->qty_accepted ?? 0);
            $qtyRejected = max(0.0, (float) ($item->qty_rejected ?? 0));
            $qtyReceived = max((float) ($item->qty_received ?? 0), $qtyAccepted + $qtyRejected);

            $item->qty_received = $qtyReceived;
            $item->qty_accepted = min($qtyAccepted, $qtyReceived);
            $item->qty_rejected = min($qtyRejected, max(0.0, $qtyReceived - $item->qty_accepted));
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_receipt_id' => null,
            'purchase_order_item_id' => null,
            'product_id' => null,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'reason_rejected' => $this->faker->optional()->sentence(),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'pending',
            'rak_id' => null,
        ];
    }
}
