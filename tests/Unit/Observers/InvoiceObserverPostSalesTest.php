<?php

namespace Tests\Unit\Observers;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Observers\InvoiceObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'tax'             => 11_000_000,
            'total'           => 111_000_000,
            'invoice_date'    => now()->toDateString(),
        ], $overrides));

        $invoice->saveQuietly();

        return $invoice;
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

    // ─── Bug C: max() removed — invoice->tax is authoritative ───────────────

    /** @test */
    public function ppn_keluaran_credit_uses_stored_invoice_tax_not_derived_value(): void
    {
        $this->makeCoas();

        // Force the derived value (total - subtotal) to differ from stored tax
        // stored tax = 11,000,000  vs  derived = 111,500,000 - 100,000,000 - 0 = 11,500,000
        $invoice = $this->makeInvoice([
            'subtotal' => 100_000_000,
            'tax'      => 11_000_000,
            'total'    => 111_500_000, // deliberately 500k more than subtotal + tax
        ]);

        $this->observer->postSalesInvoice($invoice);

        $ppnEntry = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('description', 'LIKE', '%PPn Keluaran%')
            ->first();

        $this->assertNotNull($ppnEntry, 'PPn Keluaran entry should have been created');
        $this->assertEquals(11_000_000, (float) $ppnEntry->credit,
            'credit must equal invoice->tax (11,000,000), NOT max(tax, derived) = 11,500,000'
        );
    }

    // ─── Bug D: Duplicate posting guard ──────────────────────────────────────

    /** @test */
    public function calling_post_twice_does_not_create_duplicate_entries(): void
    {
        $this->makeCoas();

        $invoice = $this->makeInvoice();

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
}
