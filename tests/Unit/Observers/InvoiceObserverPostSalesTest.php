<?php

namespace Tests\Unit\Observers;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Observers\InvoiceObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests for InvoiceObserver::postSalesInvoice() after bug-fixes:
 *
 *  [Bug A] dd() removed — missing COA now throws RuntimeException, not dumps & dies.
 *  [Bug B] DB::transaction() wrapping — partial journals are rolled back on failure.
 *  [Bug C] max() tax formula removed — stored invoice->tax is authoritative.
 *  [Bug D] Duplicate posting guard.
 */
class InvoiceObserverPostSalesTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->observer = new InvoiceObserver();

        // Minimal branch needed by factories
        Cabang::factory()->create(['kode' => 'MAIN', 'nama' => 'Main']);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function makeCoas(): void
    {
        ChartOfAccount::create(['code' => '1120',    'name' => 'Piutang Dagang',  'type' => 'Asset']);
        ChartOfAccount::create(['code' => '4000',    'name' => 'Penjualan',       'type' => 'Revenue']);
        ChartOfAccount::create(['code' => '2120.06', 'name' => 'PPn Keluaran',    'type' => 'Liability']);
        ChartOfAccount::create(['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset']);
        ChartOfAccount::create(['code' => '5100.10', 'name' => 'HPP Barang',      'type' => 'Expense']);
        ChartOfAccount::create(['code' => '6100.02', 'name' => 'Biaya Pengiriman','type' => 'Expense']);
        ChartOfAccount::create(['code' => '4100.01', 'name' => 'Diskon Penjualan','type' => 'Expense']);
    }

    private function makeInvoice(array $overrides = []): Invoice
    {
        // factory()->make() + saveQuietly() bypasses all model observers,
        // preventing InvoiceObserver::created() from firing and trying to
        // create AccountReceivable without a real SaleOrder.
        $invoice = Invoice::factory()->make(array_merge([
            'from_model_type' => SaleOrder::class,
            'from_model_id'   => 1, // dummy – not loaded in these tests
            'subtotal'        => 100_000_000,
            // BUG FIX: invoice->tax stores the rate (e.g. 11 for 11%), NOT the monetary amount.
            // The old design stored 11_000_000 here which caused catastrophically wrong journals.
            'tax'             => 11,  // 11% rate
            'ppn_rate'        => 11,
            'total'           => 111_000_000,
            'invoice_date'    => now()->toDateString(),
        ], $overrides));

        $invoice->saveQuietly();

        return $invoice;
    }

    private function attachInvoiceItemWithCostBasis(Invoice $invoice, array $overrides = []): void
    {
        $product = Product::factory()->create(array_merge([
            'cost_price' => 20_000,
            'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
            'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
            'sales_coa_id' => ChartOfAccount::where('code', '4000')->value('id'),
        ], $overrides['product'] ?? []));

        InvoiceItem::factory()->create(array_merge([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50_000,
            'subtotal' => 100_000,
            'total' => 100_000,
            'tax_rate' => 0,
            'tax_amount' => 0,
        ], $overrides['item'] ?? []));
    }

    // ─── Bug A: dd() replaced by RuntimeException ────────────────────────────

    /** @test */
    public function it_throws_runtime_exception_when_ar_coa_is_missing(): void
    {
        // Only Revenue COA, no AR COA (code 1120)
        ChartOfAccount::create(['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue']);

        $invoice = $this->makeInvoice();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/COA mapping tidak ditemukan/');

        $this->observer->postSalesInvoice($invoice);
    }

    /** @test */
    public function it_throws_runtime_exception_when_revenue_coa_is_missing(): void
    {
        // Only AR COA, no Revenue COA (code 4000)
        ChartOfAccount::create(['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset']);

        $invoice = $this->makeInvoice();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/COA mapping tidak ditemukan/');

        $this->observer->postSalesInvoice($invoice);
    }

    // ─── Bug B: DB::transaction — no partial journals left on failure ─────────

    /** @test */
    public function no_partial_journals_remain_after_missing_coa_exception(): void
    {
        // Only AR COA — revenue posting will throw inside the transaction
        ChartOfAccount::create(['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset']);

        $invoice = $this->makeInvoice();

        try {
            $this->observer->postSalesInvoice($invoice);
        } catch (\Throwable) {
            // expected
        }

        $count = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->count();

        $this->assertEquals(0, $count,
            'DB::transaction must roll back ALL entries when an exception occurs mid-posting'
        );
    }

    // ─── Bug C: sum(items.tax_amount) used as primary; rate-based fallback for item-less invoices ───

    /** @test */
    public function ppn_keluaran_credit_uses_stored_invoice_tax_not_derived_value(): void
    {
        $this->makeCoas();

        // invoice->tax = 11 (rate %), subtotal = 100,000,000
        // derived from total = 111_500_000 - 100_000_000 = 11_500_000  (deliberately different)
        // expected PPN credit = subtotal * rate/100 = 100,000,000 * 11% = 11,000,000
        // (rate-based, not "total - subtotal" derived)
        $invoice = $this->makeInvoice([
            'subtotal'  => 100_000_000,
            'tax'       => 11,          // rate in percent (NOT monetary amount)
            'total'     => 111_500_000, // intentional 500k discrepancy to verify derived is NOT used
        ]);
        $this->attachInvoiceItemWithCostBasis($invoice);

        $this->observer->postSalesInvoice($invoice);

        $ppnEntry = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('description', 'LIKE', '%PPn Keluaran%')
            ->first();

        $this->assertNotNull($ppnEntry, 'PPn Keluaran entry should have been created');
        // With no invoice items, falls back to: subtotal * (tax_rate / 100) = 100,000,000 * 11% = 11,000,000
        $this->assertEquals(11_000_000, (float) $ppnEntry->credit,
            'credit must equal subtotal * rate/100 = 11,000,000, NOT derived (total - subtotal) = 11,500,000'
        );
    }

    // ─── Bug D: Duplicate posting guard ──────────────────────────────────────

    /** @test */
    public function calling_post_twice_does_not_create_duplicate_entries(): void
    {
        $this->makeCoas();

        $invoice = $this->makeInvoice();
        $this->attachInvoiceItemWithCostBasis($invoice);

        $this->observer->postSalesInvoice($invoice);
        $countAfterFirst = JournalEntry::where('source_id', $invoice->id)->count();

        $this->observer->postSalesInvoice($invoice); // second call — must be no-op
        $countAfterSecond = JournalEntry::where('source_id', $invoice->id)->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond,
            'Second call to postSalesInvoice must not create additional journal entries'
        );
    }

    // ─── Structured logging ───────────────────────────────────────────────────

    /** @test */
    public function successful_posting_writes_info_log(): void
    {
        $this->makeCoas();

        Log::spy();

        $invoice = $this->makeInvoice();
        $this->attachInvoiceItemWithCostBasis($invoice);
        $this->observer->postSalesInvoice($invoice);

        Log::shouldHaveReceived('info')
            ->withArgs(fn($msg) => str_contains($msg, 'postSalesInvoice'))
            ->atLeast()->once();
    }

    /** @test */
    public function missing_coa_writes_error_log_before_throwing(): void
    {
        // No COAs at all
        Log::spy();

        $invoice = $this->makeInvoice();

        try {
            $this->observer->postSalesInvoice($invoice);
        } catch (\Throwable) {}

        Log::shouldHaveReceived('error')
            ->withArgs(fn($msg) => str_contains($msg, 'postSalesInvoice'))
            ->once();
    }

    /** @test */
    public function it_throws_when_cost_of_sales_cannot_be_derived_and_rolls_back_sales_journals(): void
    {
        $this->makeCoas();

        $invoice = $this->makeInvoice();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sales invoice tidak dapat diposting karena HPP / release barang terkirim tidak dapat dihitung');

        try {
            $this->observer->postSalesInvoice($invoice);
        } finally {
            $count = JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->count();

            $this->assertSame(0, $count);
        }
    }

    /** @test */
    public function it_can_post_using_configured_non_legacy_coa_codes(): void
    {
        Config::set('coa.accounts_receivable', '1120.10');
        Config::set('coa.sales_revenue', '4111');
        Config::set('coa.sales_output_vat', '2120.99');
        Config::set('coa.sales_shipping', '6100.22');

        ChartOfAccount::create(['code' => '1120.10', 'name' => 'Piutang Dagang Regional', 'type' => 'Asset']);
        ChartOfAccount::create(['code' => '4111', 'name' => 'Pendapatan Penjualan', 'type' => 'Revenue']);
        ChartOfAccount::create(['code' => '2120.99', 'name' => 'PPN Keluaran Regional', 'type' => 'Liability']);
        ChartOfAccount::create(['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset']);
        ChartOfAccount::create(['code' => '5100.10', 'name' => 'HPP Barang', 'type' => 'Expense']);
        ChartOfAccount::create(['code' => '6100.22', 'name' => 'Biaya Pengiriman Regional', 'type' => 'Expense']);
        ChartOfAccount::create(['code' => '4100.01', 'name' => 'Diskon Penjualan', 'type' => 'Expense']);

        $invoice = $this->makeInvoice([
            'tax' => 0,
            'ppn_rate' => 0,
            'ar_coa_id' => ChartOfAccount::where('code', '1120.10')->value('id'),
            'revenue_coa_id' => ChartOfAccount::where('code', '4111')->value('id'),
            'ppn_keluaran_coa_id' => ChartOfAccount::where('code', '2120.99')->value('id'),
        ]);

        $this->attachInvoiceItemWithCostBasis($invoice, [
            'product' => [
                'sales_coa_id' => ChartOfAccount::where('code', '4111')->value('id'),
            ],
        ]);

        $this->observer->postSalesInvoice($invoice);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'coa_id' => ChartOfAccount::where('code', '1120.10')->value('id'),
            'description' => 'Sales Invoice - Accounts Receivable',
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'coa_id' => ChartOfAccount::where('code', '4111')->value('id'),
        ]);
    }
}
