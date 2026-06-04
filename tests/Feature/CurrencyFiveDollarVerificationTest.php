<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\AccountReceivable;
use App\Models\Currency;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyFiveDollarVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $idr;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idr = Currency::create([
            'name' => 'IDR',
            'symbol' => 'Rp',
            'code' => 'IDR',
            'to_rupiah' => 1,
        ]);

        $this->usd = Currency::create([
            'name' => 'USD',
            'symbol' => '$',
            'code' => 'USD',
            'to_rupiah' => 16000,
        ]);

        \App\Models\User::factory()->create();
    }

    public function test_five_usd_on_po_flow_maps_to_idr_on_invoice_payment_and_journal_keeps_usd_context(): void
    {
        $usdAmount = 5.0;
        $expectedIdr = $usdAmount * (float) $this->usd->to_rupiah; // 80,000 IDR

        \App\Models\Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create();

        PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $this->usd->id,
            'nominal' => 16000,
        ]);

        $invoice = Invoice::create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'invoice_number' => 'INV-USD-5-PO',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => $expectedIdr,
            'total' => $expectedIdr,
            'status' => 'sent',
        ]);

        $this->assertEquals($expectedIdr, (float) $invoice->total);

        $payment = VendorPayment::create([
            'supplier_id' => $po->supplier_id,
            'selected_invoices' => [$invoice->id],
            'payment_date' => now(),
            'total_payment' => $expectedIdr,
            'status' => 'paid',
        ]);

        $this->assertEquals($expectedIdr, (float) $payment->total_payment);

        $coaInvoice = ChartOfAccount::create([
            'code' => '9999.11',
            'name' => 'Test PO Invoice',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $coaPayment = ChartOfAccount::create([
            'code' => '9999.12',
            'name' => 'Test PO Payment',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $invoiceEntry = JournalEntry::create([
            'coa_id' => $coaInvoice->id,
            'date' => now(),
            'reference' => 'JE-INV-USD-5-PO',
            'description' => 'Verify PO invoice conversion',
            'debit' => $expectedIdr,
            'credit' => 0,
            'journal_type' => 'purchase',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);

        $paymentEntry = JournalEntry::create([
            'coa_id' => $coaPayment->id,
            'date' => now(),
            'reference' => 'JE-PAY-USD-5-PO',
            'description' => 'Verify PO payment conversion',
            'debit' => $expectedIdr,
            'credit' => 0,
            'journal_type' => 'payment',
            'source_type' => VendorPayment::class,
            'source_id' => $payment->id,
        ]);

        $this->assertEquals($this->usd->id, $invoiceEntry->currency_id);
        $this->assertEquals(5.0, (float) $invoiceEntry->amount_original_currency);

        $this->assertEquals($this->usd->id, $paymentEntry->currency_id);
        $this->assertEquals(5.0, (float) $paymentEntry->amount_original_currency);
    }

    public function test_five_usd_on_so_flow_stays_idr_in_journal_context_with_current_logic(): void
    {
        $usdAmount = 5.0;
        $expectedIdr = $usdAmount * (float) $this->usd->to_rupiah; // 80,000 IDR

        $customer = \App\Models\Customer::factory()->create();

        $arCoa = ChartOfAccount::create([
            'code' => '1120',
            'name' => 'Piutang Dagang Test',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        $revenueCoa = ChartOfAccount::create([
            'code' => '4000',
            'name' => 'Penjualan Test',
            'type' => 'Revenue',
            'is_active' => true,
        ]);
        $bankCoa = ChartOfAccount::create([
            'code' => '1112.01',
            'name' => 'Kas/Bank Test',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        $cogsCoa = ChartOfAccount::create([
            'code' => '5100.10',
            'name' => 'HPP Test',
            'type' => 'Expense',
            'is_active' => true,
        ]);
        $goodsDeliveryCoa = ChartOfAccount::create([
            'code' => '1140.20',
            'name' => 'Release Barang Test',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'cost_price' => 20000,
            'sell_price' => $expectedIdr,
            'sales_coa_id' => $revenueCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
        ]);

        $so = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'currency_id' => $this->usd->id,
            'exchange_rate' => 16000,
            'total_amount' => $expectedIdr,
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $usdAmount,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'warehouse_id' => \App\Models\Warehouse::factory()->create()->id,
            'rak_id' => \App\Models\Rak::factory()->create(['warehouse_id' => \App\Models\Warehouse::first()->id])->id,
        ]);

        $so->update(['status' => 'completed']);

        $invoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $so->id)
            ->firstOrFail();

        $this->assertEquals($expectedIdr, (float) $invoice->total);
        $this->assertEquals($this->usd->id, $invoice->currency_id);
        $this->assertEquals(16000.0, (float) $invoice->exchange_rate);

        $accountReceivable = AccountReceivable::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals($expectedIdr, (float) $accountReceivable->total);
        $this->assertEquals(5.0, (float) $accountReceivable->total_original);
        $this->assertEquals(5.0, (float) $accountReceivable->remaining_original);
        $this->assertEquals($this->usd->id, $accountReceivable->currency_id);
        $this->assertEquals(16000.0, (float) $accountReceivable->exchange_rate);

        $invoiceEntries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertTrue($invoiceEntries->isNotEmpty(), 'Sales invoice should create journal entries');

        $arEntry = $invoiceEntries->firstWhere('coa_id', $arCoa->id);
        $this->assertNotNull($arEntry, 'Sales invoice should create an Accounts Receivable entry');
        $this->assertEquals($expectedIdr, (float) $arEntry->debit);
        $this->assertEquals($this->usd->id, $arEntry->currency_id);
        $this->assertEquals(16000.0, (float) $arEntry->exchange_rate);
        $this->assertEquals(5.0, (float) $arEntry->amount_original_currency);

        $receipt = CustomerReceipt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'selected_invoices' => [$invoice->id],
            'payment_date' => now(),
            'total_payment' => $expectedIdr,
            'payment_method' => 'Cash',
            'coa_id' => $bankCoa->id,
            'status' => 'paid',
        ]);

        $this->assertEquals($expectedIdr, (float) $receipt->total_payment);

        $receiptEntries = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->get();

        $this->assertTrue($receiptEntries->isNotEmpty(), 'Customer receipt should create journal entries');

        $cashEntry = $receiptEntries->firstWhere('coa_id', $bankCoa->id);
        $this->assertNotNull($cashEntry, 'Customer receipt should create a cash/bank entry');
        $this->assertEquals($expectedIdr, (float) $cashEntry->debit);
        $this->assertEquals($this->usd->id, $cashEntry->currency_id);
        $this->assertEquals(16000.0, (float) $cashEntry->exchange_rate);
        $this->assertEquals(5.0, (float) $cashEntry->amount_original_currency);

        $receipt = $receipt->fresh();
        $this->assertEquals($this->usd->id, $receipt->currency_id);
        $this->assertEquals(16000.0, (float) $receipt->exchange_rate);
        $this->assertEquals($expectedIdr, (float) $receipt->total_payment_idr);

        $accountReceivable = $accountReceivable->fresh();
        $this->assertEquals($expectedIdr, (float) $accountReceivable->paid);
        $this->assertEquals(0.0, (float) $accountReceivable->remaining);
        $this->assertEquals(5.0, (float) $accountReceivable->paid_original);
        $this->assertEquals(0.0, (float) $accountReceivable->remaining_original);
    }
}
