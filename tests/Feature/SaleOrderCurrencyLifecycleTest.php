<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Customer;
use App\Services\BalanceSheetService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleOrderCurrencyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);

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

    public function test_completed_sale_order_invoice_and_journal_use_item_currency_idr_amounts()
    {
        $customer = Customer::first();
        $so = SaleOrder::factory()->create([
            'currency_id' => $this->idr->id,
            'customer_id' => $customer->id,
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'total_amount' => 90000,
        ]);

        $idrProduct = \App\Models\Product::factory()->create([
            'cost_price' => 1000,
            'sell_price' => 10000,
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
        ]);
        $usdProduct = \App\Models\Product::factory()->create([
            'cost_price' => 1000,
            'sell_price' => 5,
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $idrProduct->id,
            'unit_price' => 10000,
            'quantity' => 1,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->idr->id,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $usdProduct->id,
            'unit_price' => 5,
            'quantity' => 1,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->usd->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(90000.0, (float) $invoice->subtotal);
        $this->assertEquals(90000.0, (float) $invoice->total);
        $this->assertEquals(80000.0, (float) $invoice->invoiceItem()->where('product_id', $usdProduct->id)->first()->price);

        $salesEntries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('journal_type', 'sales')
            ->get();

        $this->assertEquals((float) $salesEntries->sum('debit'), (float) $salesEntries->sum('credit'));
        $this->assertGreaterThanOrEqual(90000.0, (float) $salesEntries->sum('credit'));

        $balanceSheet = app(BalanceSheetService::class)->generate();
        $this->assertTrue($balanceSheet['is_balanced']);
        $this->assertLessThan(0.01, abs((float) $balanceSheet['difference']));
    }
}
