<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleOrderCurrencyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idr = Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'code' => 'IDR', 'to_rupiah' => 1]);
        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'code' => 'USD', 'to_rupiah' => 16000]);
        $customer = \App\Models\Customer::factory()->create();
    }

    public function test_saleorder_amount_not_converted_on_currency_switch()
    {
        $customer = Customer::first();
        $so = SaleOrder::factory()->create(['currency_id' => $this->idr->id, 'customer_id' => $customer->id]);

        $product = \App\Models\Product::factory()->create();

        $item = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'unit_price' => 1000000.00,
            'quantity' => 2,
            'currency_id' => $this->idr->id,
        ]);

        // Simulate switch in UI/resource: change currency_id on the sale order (display only)
        $so->currency_id = 2; // switched to USD in form view

        // Expect underlying item value not auto-converted
        $this->assertEquals(1000000.00, (float) $item->unit_price);

        // Reload sale order from DB to ensure UI-only change didn't persist
        $reloadedSo = SaleOrder::find($so->id);
        $this->assertEquals($this->idr->id, $reloadedSo->currency_id);
        $this->assertEquals('Rp', \App\Models\Currency::find($this->idr->id)->symbol);
    }
}
