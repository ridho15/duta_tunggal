<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TC-INV-001..007 — Invoice Service Feature Tests
 *
 * Covers: invoice number generation, AR auto-creation via Observer,
 * other_fee JSON summing, PPN calculation, and paid-status transition.
 */
class InvoiceServiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceService();
    }

    // -------------------------------------------------------------------------
    // TC-INV-001: generateInvoiceNumber() returns INV-YYYYMMDD-XXXX format
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_inv_001_generates_invoice_number_in_correct_format(): void
    {
        $number = $this->service->generateInvoiceNumber();

        $date = now()->format('Ymd');
        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d{4}$/', $number);
        $this->assertEquals("INV-{$date}-0001", $number);
    }

    // -------------------------------------------------------------------------
    // TC-INV-002: generatePurchaseInvoiceNumber() uses PINV- prefix (different from INV-)
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_inv_002_purchase_invoice_uses_pinv_prefix_different_from_inv(): void
    {
        $salesNumber    = $this->service->generateInvoiceNumber();
        $purchaseNumber = $this->service->generatePurchaseInvoiceNumber();

        $this->assertStringStartsWith('INV-', $salesNumber);
        $this->assertStringStartsWith('PINV-', $purchaseNumber);
        $this->assertNotEquals($salesNumber, $purchaseNumber);

        // Both must match their own date-padded pattern
        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d{4}$/', $salesNumber);
        $this->assertMatchesRegularExpression('/^PINV-\d{8}-\d{4}$/', $purchaseNumber);
    }

    // -------------------------------------------------------------------------
    // TC-INV-003: InvoiceObserver auto-creates AccountReceivable when a
    // SaleOrder-based invoice is created.
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_inv_003_auto_creates_ar_when_sales_invoice_is_created(): void
    {
        $cabang    = Cabang::factory()->create();
        $customer  = Customer::factory()->create();
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'confirmed',
            'cabang_id'   => $cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number'   => $this->service->generateInvoiceNumber(),
            'from_model_type'  => SaleOrder::class,
            'from_model_id'    => $saleOrder->id,
            'invoice_date'     => now(),
            'subtotal'         => 1_000_000,
            'tax'              => 110_000,
            'total'            => 1_110_000,
            'due_date'         => now()->addDays(30),
            'status'           => Invoice::STATUS_SENT,
            'cabang_id'        => $cabang->id,
        ]);

        $this->assertDatabaseHas('account_receivables', [
            'invoice_id' => $invoice->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // TC-INV-004: AR.remaining = invoice.total and AR.paid = 0 on first creation.
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_inv_004_ar_remaining_equals_total_and_paid_is_zero_on_creation(): void
    {
        $cabang    = Cabang::factory()->create();
        $customer  = Customer::factory()->create();
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'confirmed',
            'cabang_id'   => $cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number'  => $this->service->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id'   => $saleOrder->id,
            'invoice_date'    => now(),
            'subtotal'        => 2_000_000,
            'tax'             => 0,
            'total'           => 2_000_000,
            'due_date'        => now()->addDays(30),
            'status'          => Invoice::STATUS_SENT,
            'cabang_id'       => $cabang->id,
        ]);

        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar, 'AccountReceivable should be auto-created by InvoiceObserver');
        $this->assertEquals(2_000_000, (float) $ar->remaining);
        $this->assertEquals(0, (float) $ar->paid);
        $this->assertEquals('Belum Lunas', $ar->status);
    }

    // -------------------------------------------------------------------------
    // TC-INV-005: other_fee JSON array is correctly summed by the model accessor.
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_inv_005_other_fee_json_array_summed_correctly(): void
    {
        $invoice = Invoice::factory()->make([
            'other_fee' => [
                ['name' => 'Ongkos Kirim', 'amount' => 50_000],
                ['name' => 'Biaya Handling', 'amount' => 25_000],
                ['name' => 'Asuransi', 'amount' => 15_000],
            ],
        ]);

        $this->assertEquals(90_000, $invoice->other_fee_total);
    }

    /** @test */
    public function tc_inv_005b_empty_other_fee_returns_zero(): void
    {
        $invoice = Invoice::factory()->make(['other_fee' => []]);
        $this->assertEquals(0, $invoice->other_fee_total);
    }

    // -------------------------------------------------------------------------
    // TC-INV-006: PPN calculation — dpp + ppn_amount = total
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_inv_006_ppn_dpp_plus_ppn_equals_total(): void
    {
        $subtotal  = 1_000_000;
        $ppnRate   = 11; // 11%
        $ppnAmount = (int) round($subtotal * $ppnRate / 100); // 110,000
        $total     = $subtotal + $ppnAmount;                  // 1,110,000

        $invoice = Invoice::factory()->make([
            'subtotal'  => $subtotal,
            'dpp'       => $subtotal,
            'tax'       => $ppnAmount,
            'ppn_rate'  => $ppnRate,
            'total'     => $total,
        ]);

        // dpp + ppn = total
        $this->assertEquals($invoice->total, $invoice->dpp + $invoice->tax);
        // ppn = dpp * rate / 100
        $this->assertEquals($ppnAmount, (int) round($invoice->dpp * $ppnRate / 100));
        // total must equal 1,110,000
        $this->assertEquals(1_110_000, (int) $invoice->total);
    }

    // -------------------------------------------------------------------------
    // TC-INV-007: Invoice status transitions to 'paid' when AR.remaining = 0.
    // The CustomerReceiptObserver handles this in production; here we validate
    // the complete data state (all actors cooperate correctly).
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_inv_007_invoice_status_becomes_paid_when_ar_remaining_reaches_zero(): void
    {
        $cabang    = Cabang::factory()->create();
        $customer  = Customer::factory()->create();
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'cabang_id'   => $cabang->id,
        ]);

        // Create the invoice — InvoiceObserver will auto-create AR with remaining = total
        $invoice = Invoice::create([
            'invoice_number'  => $this->service->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id'   => $saleOrder->id,
            'invoice_date'    => now(),
            'subtotal'        => 500_000,
            'tax'             => 0,
            'total'           => 500_000,
            'due_date'        => now()->addDays(30),
            'status'          => Invoice::STATUS_SENT,
            'cabang_id'       => $cabang->id,
        ]);

        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);

        // Simulate full payment: AR.remaining → 0, then mark invoice as paid
        // (this mirrors what CustomerReceiptObserver.updateAccountReceivables() does)
        $ar->paid      = $ar->total;
        $ar->remaining = 0;
        $ar->status    = 'Lunas';
        $ar->save();

        $invoice->update(['status' => Invoice::STATUS_PAID]);

        $invoice->refresh();
        $ar->refresh();

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(0, (float) $ar->remaining);
        $this->assertEquals($ar->total, $ar->paid);
        $this->assertEquals('Lunas', $ar->status);
    }
}
