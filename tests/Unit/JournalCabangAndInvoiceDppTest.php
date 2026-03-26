<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\JournalBranchResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Tests for:
 *  1. Invoice DPP auto-populate (boot creating hook)
 *  2. JournalBranchResolver unit behavior
 *  3. JournalEntry::creating auto-resolve cabang_id safety-net
 */
class JournalCabangAndInvoiceDppTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1. Invoice DPP auto-populate ────────────────────────────────────────

    /**
     * InvoiceObserver::created() only acts when from_model_type is PurchaseOrder or SaleOrder.
     * By using a different type we get zero side-effects without touching the event dispatcher,
     * so the boot 'creating' hook that auto-populates dpp still fires normally.
     *
     * @test
     */
    public function test_invoice_dpp_auto_populates_from_subtotal_when_null(): void
    {
        $cabang = Cabang::create(['kode' => 'TST', 'nama' => 'Test Branch', 'alamat' => '-']);

        $invoice = Invoice::create([
            'invoice_number'  => 'INV-DPP-001',
            'from_model_type' => 'App\\Models\\Unknown',  // neither PO nor SO → InvoiceObserver is a no-op
            'from_model_id'   => 1,
            'invoice_date'    => '2024-01-15',
            'due_date'        => '2024-02-15',
            'subtotal'        => 2000000,
            'total'           => 2220000,
            'ppn_rate'        => 11,
            'tax'             => 11,
            'dpp'             => null, // deliberately left null — boot hook should fill it
            'cabang_id'       => $cabang->id,
            'status'          => 'draft',
        ]);

        $this->assertEquals(
            2000000,
            (int) $invoice->fresh()->dpp,
            'dpp must be auto-set to subtotal when null on create'
        );
    }

    /** @test */
    public function test_invoice_dpp_keeps_explicit_value_when_provided(): void
    {
        $cabang = Cabang::create(['kode' => 'TS2', 'nama' => 'Branch 2', 'alamat' => '-']);

        $invoice = Invoice::create([
            'invoice_number'  => 'INV-DPP-002',
            'from_model_type' => 'App\\Models\\Unknown',
            'from_model_id'   => 1,
            'invoice_date'    => '2024-01-15',
            'due_date'        => '2024-02-15',
            'subtotal'        => 2000000,
            'total'           => 2220000,
            'ppn_rate'        => 11,
            'tax'             => 11,
            'dpp'             => 1800000, // inclusive base — must be preserved
            'cabang_id'       => $cabang->id,
            'status'          => 'draft',
        ]);

        $this->assertEquals(
            1800000,
            (int) $invoice->fresh()->dpp,
            'dpp must not be overwritten when explicitly provided'
        );
    }

    // ─── 2. JournalBranchResolver unit tests ─────────────────────────────────

    /** @test */
    public function test_resolver_returns_null_for_null_source(): void
    {
        $resolver = new JournalBranchResolver();
        $this->assertNull($resolver->resolve(null));
    }

    /** @test */
    public function test_resolver_picks_up_direct_cabang_id_property(): void
    {
        $resolver = new JournalBranchResolver();

        $source = (object) ['cabang_id' => 7];
        $this->assertSame(7, $resolver->resolve($source));
    }

    /** @test */
    public function test_resolver_returns_null_when_no_strategy_matches(): void
    {
        $resolver = new JournalBranchResolver();

        // Object with no cabang_id and no meaningful relations
        $source = (object) ['id' => 1, 'name' => 'orphan'];
        $this->assertNull($resolver->resolve($source));
    }

    // ─── 3. JournalEntry::creating auto-resolve safety-net ───────────────────

    protected function makeCoa(string $code = '1100'): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['code' => $code],
            ['name' => "Test COA {$code}", 'type' => 'Asset', 'normal_balance' => 'debit']
        );
    }

    /**
     * Insert the Invoice row directly via DB to bypass InvoiceObserver entirely
     * while keeping the JournalEntry event dispatcher intact so the creating hook fires.
     */
    protected function insertInvoiceRow(array $attrs): int
    {
        return DB::table('invoices')->insertGetId(array_merge([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id'   => 9999,
            'invoice_date'    => '2024-01-15',
            'due_date'        => '2024-02-15',
            'subtotal'        => 500000,
            'total'           => 555000,
            'ppn_rate'        => 11,
            'tax'             => 11,
            'dpp'             => 500000,
            'other_fee'       => '[]',
            'status'          => 'draft',
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $attrs));
    }

    /** @test */
    public function test_journal_entry_auto_resolves_cabang_id_from_source_model(): void
    {
        $cabang = Cabang::create(['kode' => 'BR1', 'nama' => 'Branch 1', 'alamat' => '-']);
        $coa    = $this->makeCoa('1101');

        // Insert Invoice via DB (avoids InvoiceObserver) while leaving JournalEntry
        // event dispatcher fully intact so JournalEntry::creating hook can fire.
        $invoiceId = $this->insertInvoiceRow([
            'invoice_number' => 'INV-JE-001',
            'cabang_id'      => $cabang->id,
        ]);

        // Create a JournalEntry deliberately WITHOUT cabang_id
        $entry = JournalEntry::create([
            'coa_id'       => $coa->id,
            'date'         => '2024-01-15',
            'reference'    => 'TEST-JE-001',
            'description'  => 'Auto-resolve test',
            'debit'        => 500000,
            'credit'       => 0,
            'journal_type' => 'sales',
            'source_type'  => Invoice::class,
            'source_id'    => $invoiceId,
            // cabang_id intentionally omitted
        ]);

        $this->assertEquals(
            $cabang->id,
            $entry->cabang_id,
            'JournalEntry::creating should auto-resolve cabang_id from the Invoice source model'
        );
    }

    /** @test */
    public function test_journal_entry_preserves_explicit_cabang_id(): void
    {
        $cabang1 = Cabang::create(['kode' => 'C1', 'nama' => 'Cabang 1', 'alamat' => '-']);
        $cabang2 = Cabang::create(['kode' => 'C2', 'nama' => 'Cabang 2', 'alamat' => '-']);
        $coa     = $this->makeCoa('1102');

        $invoiceId = $this->insertInvoiceRow([
            'invoice_number' => 'INV-JE-002',
            'cabang_id'      => $cabang1->id, // invoice belongs to cabang1
        ]);

        // Explicitly pass cabang2 — must be respected, NOT overwritten by resolver
        $entry = JournalEntry::create([
            'coa_id'       => $coa->id,
            'date'         => '2024-01-15',
            'reference'    => 'TEST-JE-002',
            'description'  => 'Explicit cabang override test',
            'debit'        => 100000,
            'credit'       => 0,
            'journal_type' => 'sales',
            'source_type'  => Invoice::class,
            'source_id'    => $invoiceId,
            'cabang_id'    => $cabang2->id, // explicit override
        ]);

        $this->assertEquals(
            $cabang2->id,
            $entry->cabang_id,
            'Explicit cabang_id must never be overwritten by the auto-resolver'
        );
    }

    /** @test */
    public function test_journal_entry_stays_null_when_source_has_no_cabang(): void
    {
        $coa = $this->makeCoa('1103');

        $invoiceId = $this->insertInvoiceRow([
            'invoice_number' => 'INV-JE-003',
            'cabang_id'      => null,
        ]);

        $entry = JournalEntry::create([
            'coa_id'       => $coa->id,
            'date'         => '2024-01-15',
            'reference'    => 'TEST-JE-003',
            'description'  => 'Null cabang source test',
            'debit'        => 50000,
            'credit'       => 0,
            'journal_type' => 'sales',
            'source_type'  => Invoice::class,
            'source_id'    => $invoiceId,
            // no cabang_id, and source also has none
        ]);

        $this->assertNull(
            $entry->cabang_id,
            'cabang_id should remain null when the source model also has no cabang_id'
        );
    }
}


