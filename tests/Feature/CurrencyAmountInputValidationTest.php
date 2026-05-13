<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyAmountInputValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idr = Currency::create([
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'code' => 'IDR',
            'to_rupiah' => 1,
        ]);

        $this->usd = Currency::create([
            'name' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'to_rupiah' => 16000,
        ]);
        \App\Models\User::factory()->create();
    }

    public function test_idr_entry_stored_correctly()
    {
        $order = OrderRequest::create([
            'request_number' => 'RQ-TEST-IDR',
            'request_date' => now(),
            'currency_id' => $this->idr->id,
            'created_by' => User::first()->id,
        ]);

        $product = \App\Models\Product::factory()->create();

        $item = OrderRequestItem::create([
            'order_request_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 1000000.00,
            'quantity' => 1,
            'currency_id' => $this->idr->id,
        ]);

        $this->assertEquals(1000000.00, (float) $item->unit_price);
        $this->assertEquals('Rp', $item->currency->symbol);
    }

    public function test_usd_entry_not_converted_to_idr()
    {
        $order = OrderRequest::create([
            'request_number' => 'RQ-TEST-USD',
            'request_date' => now(),
            'currency_id' => $this->usd->id,
            'created_by' => User::first()->id,
        ]);

        $product2 = \App\Models\Product::factory()->create();

        $item = OrderRequestItem::create([
            'order_request_id' => $order->id,
            'product_id' => $product2->id,
            'unit_price' => 1000.00,
            'quantity' => 1,
            'currency_id' => $this->usd->id,
        ]);

        $this->assertEquals(1000.00, (float) $item->unit_price);
        $this->assertEquals('$', $item->currency->symbol);
    }
}
