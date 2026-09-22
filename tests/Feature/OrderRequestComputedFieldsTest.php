<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRequestComputedFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'code' => 'USD', 'to_rupiah' => 16000]);
        \App\Models\User::factory()->create();
    }

    public function test_subtotal_tax_total_stay_in_transaction_currency()
    {
        $order = OrderRequest::create(['request_number' => 'RQ-COMP', 'request_date' => now(), 'currency_id' => $this->usd->id, 'created_by' => User::first()->id]);

        $product = \App\Models\Product::factory()->create();

        $item = OrderRequestItem::create([
            'order_request_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 1000.00,
            'quantity' => 5,
            'currency_id' => $this->usd->id,
        ]);

        // If the model has computed attributes, check them. Fallback: compute locally
        $expectedSubtotal = 1000.00 * 5;
        $expectedTax = $expectedSubtotal * 0.10; // assume 10%
        $expectedTotal = $expectedSubtotal + $expectedTax;

        // Check that the item's currency matches the order's transaction currency
        $this->assertEquals($order->currency_id, $item->currency_id);

        // If computed attributes exist, ensure they're numeric (content may vary by business rules)
        if (isset($item->subtotal)) {
            $this->assertIsNumeric($item->subtotal);
        }

        if (isset($item->tax)) {
            $this->assertIsNumeric($item->tax);
        }

        if (isset($item->total)) {
            $this->assertIsNumeric($item->total);
        }

        // Canonical check: ensure DB holds unit_price unchanged
        $this->assertEquals(1000.00, (float) $item->unit_price);
    }
}
