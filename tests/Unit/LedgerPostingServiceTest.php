<?php

namespace Tests\Unit;

use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Invoice::unsetEventDispatcher();

        \App\Models\Cabang::create([
            'kode' => 'MAIN',
            'nama' => 'Main Branch',
            'alamat' => 'Main Address',
        ]);

        \App\Models\User::factory()->create();

        ChartOfAccount::create(['code' => '1140.01', 'name' => 'Persediaan Barang Dagangan', 'type' => 'Asset']);
        ChartOfAccount::create(['code' => '2110', 'name' => 'Hutang Dagang', 'type' => 'Liability']);
        ChartOfAccount::create(['code' => '2100.10', 'name' => 'Penerimaan Barang Belum Tertagih', 'type' => 'Liability']);
        ChartOfAccount::create(['code' => '1170.06', 'name' => 'PPN Masukan', 'type' => 'Asset']);
    }

    /** @test */
    public function post_invoice_without_receipt_debits_inventory()
    {
        $supplier = Supplier::factory()->create();

        $product = Product::factory()->create([
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100000,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id' => $po->id,
            'subtotal' => 100000,
            'tax' => 11000,
            'total' => 111000,
            'ppn_rate' => 11,
        ]);

        $result = app(LedgerPostingService::class)->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertCount(3, $entries);

        $inventoryEntry = $entries->where('debit', 100000)->first();
        $this->assertNotNull($inventoryEntry);
        $this->assertEquals('1140.01', $inventoryEntry->coa->code);
        $this->assertTrue(strpos($inventoryEntry->description, 'inventory') !== false);

        $ppnEntry = $entries->where('debit', 11000)->first();
        $this->assertNotNull($ppnEntry);
        $this->assertEquals('1170.06', $ppnEntry->coa->code);

        $apEntry = $entries->where('credit', 111000)->first();
        $this->assertNotNull($apEntry);
        $this->assertEquals('2110', $apEntry->coa->code);
    }

    /** @test */
    public function post_invoice_with_receipt_debits_unbilled_purchase()
    {
        $supplier = Supplier::factory()->create();

        $product = Product::factory()->create([
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100000,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => \App\Models\Cabang::first()->id,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id' => $po->id,
            'subtotal' => 100000,
            'tax' => 11000,
            'total' => 111000,
            'ppn_rate' => 11,
        ]);

        $result = app(LedgerPostingService::class)->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertCount(3, $entries);

        $unbilledEntry = $entries->where('debit', 100000)->first();
        $this->assertNotNull($unbilledEntry);
        $this->assertEquals('2100.10', $unbilledEntry->coa->code);
        $this->assertTrue(strpos($unbilledEntry->description, 'unbilled purchase') !== false);

        $ppnEntry = $entries->where('debit', 11000)->first();
        $this->assertNotNull($ppnEntry);
        $this->assertEquals('1170.06', $ppnEntry->coa->code);

        $apEntry = $entries->where('credit', 111000)->first();
        $this->assertNotNull($apEntry);
        $this->assertEquals('2110', $apEntry->coa->code);
    }

    /** @test */
    public function post_invoice_with_legacy_tax_and_other_fees_uses_tax_as_rate()
    {
        $supplier = Supplier::factory()->create();

        $product = Product::factory()->create([
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100000,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => \App\Models\Cabang::first()->id,
        ]);

        $expenseCoa = ChartOfAccount::create([
            'code' => '6100.99',
            'name' => 'Biaya Lainnya',
            'type' => 'expense',
            'is_active' => 1,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id' => $po->id,
            'subtotal' => 100000,
            'tax' => 11,
            'ppn_rate' => 0,
            'expense_coa_id' => $expenseCoa->id,
            'other_fee' => [
                ['name' => 'Biaya Transport', 'amount' => 7500],
            ],
            'total' => 118500,
        ]);

        $result = app(LedgerPostingService::class)->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertCount(4, $entries);

        $inventoryEntry = $entries->where('debit', 100000)->first();
        $this->assertNotNull($inventoryEntry);
        $this->assertEquals('2100.10', $inventoryEntry->coa->code);

        $ppnEntry = $entries->where('debit', 11000)->first();
        $this->assertNotNull($ppnEntry);
        $this->assertEquals('1170.06', $ppnEntry->coa->code);

        $otherFeeEntry = $entries->where('debit', 7500)->first();
        $this->assertNotNull($otherFeeEntry);
        $this->assertEquals($expenseCoa->code, $otherFeeEntry->coa->code);

        $apEntry = $entries->where('credit', 118500)->first();
        $this->assertNotNull($apEntry);
        $this->assertEquals('2110', $apEntry->coa->code);
    }

    /** @test */
    public function post_invoice_ignores_zero_value_other_fee_rows()
    {
        $supplier = Supplier::factory()->create();

        $product = Product::factory()->create([
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100000,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => \App\Models\Cabang::first()->id,
        ]);

        $expenseCoa = ChartOfAccount::create([
            'code' => '6100.02',
            'name' => 'Biaya Pengiriman / Pengangkutan',
            'type' => 'expense',
            'is_active' => 1,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id' => $po->id,
            'subtotal' => 100000,
            'tax' => 11,
            'ppn_rate' => 11,
            'expense_coa_id' => $expenseCoa->id,
            'other_fee' => [
                ['name' => 'Biaya Pengiriman', 'amount' => 0],
            ],
            'total' => 111000,
        ]);

        $result = app(LedgerPostingService::class)->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertCount(3, $entries);
        $this->assertNull($entries->where('coa_id', $expenseCoa->id)->first());

        $apEntry = $entries->where('credit', 111000)->first();
        $this->assertNotNull($apEntry);
        $this->assertEquals('2110', $apEntry->coa->code);
    }

    /** @test */
    public function post_invoice_ignores_fractional_rounding_residue_without_other_fees()
    {
        $supplier = Supplier::factory()->create();

        $product = Product::factory()->create([
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => 519250,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 519250,
        ]);

        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => \App\Models\Cabang::first()->id,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id' => $po->id,
            'subtotal' => 519250,
            'tax' => 11,
            'ppn_rate' => 11,
            'other_fee' => [],
            'total' => 576368,
        ]);

        $result = app(LedgerPostingService::class)->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertCount(3, $entries);
        $this->assertNull($entries->where('debit', 1)->first());
        $this->assertSame(0, (int) $invoice->other_fee_total);
    }

    /** @test */
    public function reverse_invoice_journal_entries_creates_mirror_entries_for_legacy_invoice_sources()
    {
        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'subtotal' => 100000,
            'tax' => 0,
            'ppn_rate' => 0,
            'other_fee' => [],
            'total' => 100000,
        ]);

        $coa = ChartOfAccount::where('code', '1140.01')->first();

        $originalDebit = JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now()->toDateString(),
            'reference' => $invoice->invoice_number,
            'description' => 'Legacy invoice debit',
            'debit' => 100000,
            'credit' => 0,
            'journal_type' => 'purchase',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'cabang_id' => \App\Models\Cabang::first()->id,
        ]);

        $originalCredit = JournalEntry::create([
            'coa_id' => ChartOfAccount::where('code', '2110')->first()->id,
            'date' => now()->toDateString(),
            'reference' => $invoice->invoice_number,
            'description' => 'Legacy invoice credit',
            'debit' => 0,
            'credit' => 100000,
            'journal_type' => 'purchase',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'cabang_id' => \App\Models\Cabang::first()->id,
        ]);

        $reversals = app(LedgerPostingService::class)->reverseInvoiceJournalEntries($invoice, '2026-03-31');

        $this->assertCount(2, $reversals);
        $this->assertTrue($reversals->every(fn ($entry) => $entry->is_reversal));
        $this->assertNotNull($reversals->first(fn ($entry) => (float) $entry->debit === 0.0 && (float) $entry->credit === 100000.0));
        $this->assertNotNull($reversals->first(fn ($entry) => (float) $entry->debit === 100000.0 && (float) $entry->credit === 0.0));

        $this->assertNotNull($originalDebit->fresh()->reversal_of_transaction_id);
        $this->assertNotNull($originalCredit->fresh()->reversal_of_transaction_id);
    }
}