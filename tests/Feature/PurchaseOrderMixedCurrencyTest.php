<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderMixedCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'code' => 'USD', 'to_rupiah' => 16000]);
        $this->eur = Currency::create(['name' => 'EUR', 'symbol' => '€', 'code' => 'EUR', 'to_rupiah' => 17000]);
        \App\Models\Supplier::factory()->create();
    }

    public function test_purchaseorder_allows_per_item_currency()
    {
        $supplier = Supplier::first();
        $po = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

        $product1 = \App\Models\Product::factory()->create();
        $product2 = \App\Models\Product::factory()->create();

        $item1 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product1->id,
            'unit_price' => 1000.00,
            'quantity' => 1,
            'currency_id' => $this->usd->id, // USD
        ]);

        $item2 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product2->id,
            'unit_price' => 500.00,
            'quantity' => 1,
            'currency_id' => $this->eur->id, // EUR
        ]);

        $this->assertEquals(1000.00, (float) $item1->unit_price);
        $this->assertEquals(500.00, (float) $item2->unit_price);
        $this->assertEquals('$', $item1->currency->symbol);
        $this->assertEquals('€', $item2->currency->symbol);
    }
}
