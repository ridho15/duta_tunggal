<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyAmountPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idr = Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'code' => 'IDR', 'to_rupiah' => 1]);
        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'code' => 'USD', 'to_rupiah' => 16000]);
        \App\Models\User::factory()->create();
    }

    public function test_decimal_precision_persistence()
    {
        $order = OrderRequest::create(['request_number' => 'RQ-PREC', 'request_date' => now(), 'currency_id' => $this->usd->id, 'created_by' => User::first()->id]);

        $product = \App\Models\Product::factory()->create();
        $item = OrderRequestItem::create([
            'order_request_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 1234.56,
            'quantity' => 1,
            'currency_id' => $this->usd->id,
        ]);

        $this->assertEquals(1234.56, (float) $item->unit_price);
    }

    public function test_zero_and_null_amounts()
    {
        $order = OrderRequest::create(['request_number' => 'RQ-ZERO', 'request_date' => now(), 'currency_id' => $this->idr->id, 'created_by' => User::first()->id]);

        $product2 = \App\Models\Product::factory()->create();

        $item = OrderRequestItem::create([
            'order_request_id' => $order->id,
            'product_id' => $product2->id,
            'unit_price' => 0.00,
            'quantity' => 1,
            'currency_id' => $this->idr->id,
        ]);

        $this->assertEquals(0.00, (float) $item->unit_price);
    }
}
