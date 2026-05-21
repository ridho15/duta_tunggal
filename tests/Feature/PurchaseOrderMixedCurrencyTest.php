<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\UnitOfMeasure;
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

    public function test_purchase_order_pdf_separates_expected_date_from_top_due_date_and_respects_item_currency()
    {
        $cabang = Cabang::factory()->create();
        $supplier = Supplier::first();
        $uom = UnitOfMeasure::factory()->create();

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
            'po_number' => 'PO-TEST-USD-001',
            'order_date' => Carbon::create(2026, 5, 1),
            'expected_date' => Carbon::create(2026, 5, 31),
            'tempo_hutang' => 14,
            'status' => 'approved',
            'is_asset' => false,
        ]);

        $product = Product::factory()->create([
            'uom_id' => $uom->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 0.5,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
            'currency_id' => $this->usd->id,
        ]);

        $html = view('pdf.purchase-order', [
            'purchaseOrder' => $po->load(['supplier', 'cabang', 'purchaseOrderItem.currency', 'purchaseOrderCurrency.currency']),
        ])->render();

        $this->assertStringContainsString('Tanggal Diharapkan:', $html);
        $this->assertStringContainsString('31/05/2026', $html);
        $this->assertStringContainsString('TOP:', $html);
        $this->assertStringContainsString('Credit 14 hari', $html);
        $this->assertStringContainsString('Jatuh Tempo:', $html);
        $this->assertStringContainsString('15/05/2026', $html);
        $this->assertStringContainsString('$ 0.50', $html);
        $this->assertStringContainsString('$ 5.00', $html);
        $this->assertStringContainsString('colspan="10"', $html);
        $this->assertStringContainsString('colspan="2"', $html);
    }
}
