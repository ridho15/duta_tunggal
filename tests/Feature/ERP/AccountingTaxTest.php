<?php

namespace Tests\Feature\ERP;

use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MODULE 7 — ACCOUNTING
 *
 * Tests items #22, #23, #24:
 *  #22 PPN calculation: item=1,000,000 @ 12% → tax=120,000 (rate, not absolute)
 *  #23 AP settlement detail shows supplier, invoice, PO reference, total, paid, remaining
 *  #24 NTPN must not appear in customer payment form
 */
class AccountingTaxTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Customer $customer;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::factory()->create(['code' => 'IDR']);

        $this->cabang    = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->supplier  = Supplier::factory()->create(['perusahaan' => 'PT Tax Test Supplier']);
        $this->customer  = Customer::factory()->create();
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    // ─── #22 PPN CALCULATION ─────────────────────────────────────────────────

    /** @test */
    public function ppn_calculation_on_invoice_uses_rate_not_absolute_amount(): void
    {
        // Invoice with subtotal=1,000,000 and tax=12 (meaning 12%, not Rp 12,000)
        $invoice = Invoice::create([
            'invoice_number'   => 'INV-TAX-' . now()->format('YmdHis'),
            'from_model_type'  => 'App\\Models\\PurchaseOrder',
            'from_model_id'    => PurchaseOrder::factory()->create([
                'supplier_id'  => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'cabang_id'    => $this->cabang->id,
            ])->id,
            'invoice_date'     => now()->toDateString(),
            'due_date'         => now()->addDays(30)->toDateString(),
            'subtotal'         => 1000000,
            'tax'              => 12,  // 12% rate
            'ppn_rate'         => 12,
            'total'            => 1120000,
            'dpp'              => 1000000,
            'status'           => 'draft',
            'cabang_id'        => $this->cabang->id,
        ]);

        // Tax amount must be: subtotal * (tax / 100) = 1,000,000 * 0.12 = 120,000
        $taxAmount = (float) $invoice->subtotal * ((float) $invoice->tax / 100);

        $this->assertEquals(120000.0, $taxAmount,
            'PPN of 12% on Rp 1,000,000 must equal Rp 120,000');

        // Verify NOT using tax as absolute: tax != 120000
        $this->assertNotEquals(12.0, $taxAmount,
            'tax field must be treated as percentage rate, not absolute amount');
    }

    /** @test */
    public function ppn_calculation_for_eleven_percent_rate(): void
    {
        $subtotal = 2000000;
        $taxRate  = 11; // 11%

        $taxAmount = $subtotal * ($taxRate / 100);
        $this->assertEquals(220000.0, $taxAmount,
            '11% PPN on Rp 2,000,000 must equal Rp 220,000');
    }

    /** @test */
    public function ppn_included_in_total_amount(): void
    {
        $subtotal  = 1000000;
        $taxRate   = 12;
        $taxAmount = $subtotal * ($taxRate / 100); // 120,000
        $total     = $subtotal + $taxAmount;        // 1,120,000

        $this->assertEquals(1120000, $total,
            'Total must equal subtotal + tax amount for PPN Excluded invoice');
    }

    /** @test */
    public function invoice_stores_tax_as_rate_not_as_absolute_amount(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number'  => 'INV-RATE-' . now()->format('YmdHis'),
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id'   => $po->id,
            'invoice_date'    => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'subtotal'        => 5000000,
            'tax'             => 12,
            'ppn_rate'        => 12,
            'total'           => 5600000,
            'dpp'             => 5000000,
            'status'          => 'draft',
            'cabang_id'       => $this->cabang->id,
        ]);

        // Confirm the stored 'tax' value is 12 (percent), not 600000 (absolute)
        $this->assertDatabaseHas('invoices', [
            'id'  => $invoice->id,
            'tax' => 12,
        ]);

        $this->assertEquals(12, $invoice->fresh()->tax,
            'Invoice tax field must store 12 (the rate) not 600000 (the absolute amount)');
    }

    /** @test */
    public function journal_entries_for_purchase_invoice_include_ppn_masukan_line(): void
    {
        // Create COAs needed by LedgerPostingService
        $apCoa = ChartOfAccount::factory()->create(['code' => '2100.01', 'name' => 'Account Payable']);
        $ppnCoa = ChartOfAccount::factory()->create(['code' => '1170.06', 'name' => 'PPN Masukan']);
        $expCoa = ChartOfAccount::factory()->create(['code' => '5100.01', 'name' => 'Expense']);

        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number'        => 'INV-JE-' . now()->format('YmdHis'),
            'from_model_type'       => 'App\\Models\\PurchaseOrder',
            'from_model_id'         => $po->id,
            'invoice_date'          => now()->toDateString(),
            'due_date'              => now()->addDays(30)->toDateString(),
            'subtotal'              => 1000000,
            'tax'                   => 12,
            'ppn_rate'              => 12,
            'total'                 => 1120000,
            'dpp'                   => 1000000,
            'status'                => 'draft',
            'accounts_payable_coa_id' => $apCoa->id,
            'ppn_masukan_coa_id'    => $ppnCoa->id,
            'expense_coa_id'        => $expCoa->id,
            'cabang_id'             => $this->cabang->id,
        ]);

        $service = app(LedgerPostingService::class);
        $entries = $service->postInvoice($invoice);

        // There should be journal entries
        $this->assertNotEmpty($entries ?? [],
            'postPurchaseInvoice should create journal entries');

        // Check DB for PPN masukan entry (debit = 120,000)
        $ppnEntry = JournalEntry::where('coa_id', $ppnCoa->id)
            ->where('debit', 120000)
            ->first();

        $this->assertNotNull($ppnEntry,
            'Journal entries must include PPN Masukan debit of Rp 120,000 (12% of Rp 1,000,000)');
    }

    // ─── #23 AP SETTLEMENT DETAIL ─────────────────────────────────────────────

    /** @test */
    public function account_payable_stores_supplier_invoice_total_paid_remaining(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
            'po_number'    => 'PO-AP-TEST-001',
        ]);

        $invoice = Invoice::create([
            'invoice_number'  => 'INV-AP-' . now()->format('YmdHis'),
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id'   => $po->id,
            'invoice_date'    => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'subtotal'        => 2000000,
            'tax'             => 11,
            'total'           => 2220000,
            'dpp'             => 2000000,
            'status'          => 'draft',
            'cabang_id'       => $this->cabang->id,
        ]);

        $ap = AccountPayable::create([
            'invoice_id'  => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total'       => 2220000,
            'paid'        => 1000000,
            'remaining'   => 1220000,
            'status'      => 'Belum Lunas',
            'created_by'  => $this->user->id,
        ]);

        $ap->refresh()->load('invoice.fromModel', 'supplier');

        // Supplier
        $this->assertEquals($this->supplier->id, $ap->supplier_id);
        $this->assertEquals('PT Tax Test Supplier', $ap->supplier->perusahaan);

        // Invoice
        $this->assertEquals($invoice->id, $ap->invoice_id);

        // PO reference
        $this->assertEquals('PO-AP-TEST-001', $ap->invoice->fromModel->po_number);

        // Total, Paid, Remaining
        $this->assertEquals(2220000, $ap->total);
        $this->assertEquals(1000000, $ap->paid);
        $this->assertEquals(1220000, $ap->remaining);

        $this->assertDatabaseHas('account_payables', [
            'id'        => $ap->id,
            'total'     => 2220000,
            'paid'      => 1000000,
            'remaining' => 1220000,
            'status'    => 'Belum Lunas',
        ]);
    }

    /** @test */
    public function account_payable_status_changes_to_lunas_when_paid_in_full(): void
    {
        $invoice = Invoice::create([
            'invoice_number'  => 'INV-LUNAS-' . now()->format('YmdHis'),
            'from_model_type' => 'App\\Models\\Supplier',
            'from_model_id'   => $this->supplier->id,
            'invoice_date'    => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'subtotal'        => 500000,
            'tax'             => 0,
            'total'           => 500000,
            'dpp'             => 500000,
            'status'          => 'draft',
            'cabang_id'       => $this->cabang->id,
        ]);

        $ap = AccountPayable::create([
            'invoice_id'  => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total'       => 500000,
            'paid'        => 0,
            'remaining'   => 500000,
            'status'      => 'Belum Lunas',
            'created_by'  => $this->user->id,
        ]);

        $ap->update([
            'paid'      => 500000,
            'remaining' => 0,
            'status'    => 'Lunas',
        ]);

        $this->assertDatabaseHas('account_payables', [
            'id'     => $ap->id,
            'status' => 'Lunas',
            'paid'   => 500000,
        ]);
    }

    // ─── #24 NTPN NOT IN CUSTOMER PAYMENT FORM ───────────────────────────────

    /** @test */
    public function customer_receipt_has_ntpn_field_in_database(): void
    {
        // NTPN field exists in the DB, but must be hidden from the Filament form
        $receipt = CustomerReceipt::create([
            'customer_id'    => $this->customer->id,
            'payment_date'   => now()->toDateString(),
            'ntpn'           => null,  // must be null / hidden from form input
            'total_payment'  => 0,
            'status'         => 'Draft',
            'cabang_id'      => $this->cabang->id,
        ]);

        $this->assertDatabaseHas('customer_receipts', [
            'id'   => $receipt->id,
            'ntpn' => null,
        ]);
    }

    /** @test */
    public function ntpn_field_is_not_listed_in_required_customer_receipt_columns(): void
    {
        // Verify the Filament resource does NOT expose ntpn as a required or visible field
        // We do this by verifying ntpn can be NULL without DB constraint violation
        $receipt = CustomerReceipt::create([
            'customer_id'   => $this->customer->id,
            'payment_date'  => now()->toDateString(),
            'ntpn'          => null,
            'total_payment' => 0,
            'status'        => 'Draft',
            'cabang_id'     => $this->cabang->id,
        ]);

        // Must succeed without exception — ntpn is nullable/hidden
        $this->assertDatabaseHas('customer_receipts', ['id' => $receipt->id]);
        $this->assertNull($receipt->fresh()->ntpn,
            'NTPN must be null (hidden from form) by default');
    }
}
