<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderRequestItem>
 */
class OrderRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = $this->faker->numberBetween(5000, 500000);
        $orderRequest = OrderRequest::withoutGlobalScopes()->inRandomOrder()->first() ?? OrderRequest::factory()->create();

        // product and supplier resolution
        $product = Product::inRandomOrder()->first();
        if (! $product) {
            $product = Product::factory()->create();
        }

        $supplierId = $product->suppliers()->inRandomOrder()->value('suppliers.id') ?? $product->supplier_id ?? Supplier::query()->inRandomOrder()->value('id');
        if (! $supplierId) {
            $supplierId = Supplier::factory()->create()->id;
        }

        $cabangId = $product?->cabang_id ?? Cabang::inRandomOrder()->first()?->id ?? Cabang::factory()->create()->id;

        // inherit currency from parent order request when available
        $currencyId = $orderRequest->currency_id ?? Currency::where('code', 'IDR')->value('id') ?? Currency::query()->inRandomOrder()->value('id');

        $status = in_array($orderRequest->status, ['approved', 'partial', 'complete', 'closed'], true)
            ? 'approved'
            : ($orderRequest->status === 'rejected' ? 'rejected' : 'draft');

        return [
            'order_request_id' => $orderRequest->id,
            'cabang_id' => $cabangId,
            'product_id' => $product->id,
            'supplier_id' => $supplierId,
            'quantity' => random_int(1, 20),
            'unit_price' => $unitPrice,
            'original_price' => $unitPrice,
            'tax' => 0,
            'tipe_pajak' => 'eklusif',
            'status' => $status,
            'currency_id' => $currencyId,
            'note' => $this->faker->sentence()
        ];
    }
}
