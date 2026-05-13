<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\SaleOrder;
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

        $so = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'currency_id' => $this->usd->id,
            'total_amount' => $expectedIdr,
            'status' => 'approved',
        ]);

        // Required by InvoiceObserver for sales invoice posting path.
        ChartOfAccount::create([
            'code' => '1120',
            'name' => 'Piutang Dagang Test',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        ChartOfAccount::create([
            'code' => '4000',
            'name' => 'Penjualan Test',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'invoice_number' => 'INV-USD-5-SO',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => $expectedIdr,
            'total' => $expectedIdr,
            'status' => 'sent',
        ]);

        $this->assertEquals($expectedIdr, (float) $invoice->total);

        $receipt = CustomerReceipt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'selected_invoices' => [$invoice->id],
            'payment_date' => now(),
            'total_payment' => $expectedIdr,
            'status' => 'draft',
        ]);

        $this->assertEquals($expectedIdr, (float) $receipt->total_payment);

        $coaInvoice = ChartOfAccount::create([
            'code' => '9999.13',
            'name' => 'Test SO Invoice',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $coaReceipt = ChartOfAccount::create([
            'code' => '9999.14',
            'name' => 'Test SO Payment',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $invoiceEntry = JournalEntry::create([
            'coa_id' => $coaInvoice->id,
            'date' => now(),
            'reference' => 'JE-INV-USD-5-SO',
            'description' => 'Verify SO invoice conversion',
            'debit' => $expectedIdr,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);

        $receiptEntry = JournalEntry::create([
            'coa_id' => $coaReceipt->id,
            'date' => now(),
            'reference' => 'JE-REC-USD-5-SO',
            'description' => 'Verify SO payment conversion',
            'debit' => $expectedIdr,
            'credit' => 0,
            'journal_type' => 'receipt',
            'source_type' => CustomerReceipt::class,
            'source_id' => $receipt->id,
        ]);

        // Current hook resolves explicit PO currency paths; SO/CustomerReceipt falls back to IDR.
        $this->assertEquals($this->idr->id, $invoiceEntry->currency_id);
        $this->assertEquals($expectedIdr, (float) $invoiceEntry->amount_original_currency);

        $this->assertEquals($this->idr->id, $receiptEntry->currency_id);
        $this->assertEquals($expectedIdr, (float) $receiptEntry->amount_original_currency);
    }
}
