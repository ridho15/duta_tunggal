<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConsistencyReloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idr = Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'code' => 'IDR', 'to_rupiah' => 1]);
        \App\Models\Customer::factory()->create();
    }

    public function test_save_and_reload_preserves_amounts()
    {
        $customer = Customer::first();
        $so = SaleOrder::factory()->create(['currency_id' => $this->idr->id, 'customer_id' => $customer->id]);

        $product = \App\Models\Product::factory()->create();

        $item = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'unit_price' => 250000.50,
            'quantity' => 3,
            'currency_id' => $this->idr->id,
        ]);

        $reloadedItem = SaleOrderItem::find($item->id);

        $this->assertEquals(250000.50, (float) $reloadedItem->unit_price);
    }
}
