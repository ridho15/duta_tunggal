<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LedgerPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable InvoiceObserver for testing
        \App\Models\Invoice::unsetEventDispatcher();

        // Create cabang
        \App\Models\Cabang::create([
            'kode' => 'MAIN',
            'nama' => 'Main Branch',
            'alamat' => 'Main Address',
        ]);

        // Create user
        \App\Models\User::factory()->create();

        // Create necessary COAs
        ChartOfAccount::create(['code' => '1140.01', 'name' => 'Persediaan Barang Dagangan', 'type' => 'Asset']);
        ChartOfAccount::create(['code' => '2110', 'name' => 'Hutang Dagang', 'type' => 'Liability']);
        ChartOfAccount::create(['code' => '2100.10', 'name' => 'Penerimaan Barang Belum Tertagih', 'type' => 'Liability']);
        ChartOfAccount::create(['code' => '1170.06', 'name' => 'PPN Masukan', 'type' => 'Asset']);
    }

    /** @test */
    public function post_invoice_without_receipt_debits_inventory()
    {
        // Create supplier
        $supplier = Supplier::factory()->create();

        // Create product
        $product = Product::factory()->create([
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);

        // Create PO
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

        // Create invoice from PO (no receipt)
        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\Models\PurchaseOrder',
            'from_model_id' => $po->id,
            'subtotal' => 100000,
            'tax' => 11000,
            'total' => 111000,
            'ppn_rate' => 11,
        ]);

        // Post invoice
        $service = app(LedgerPostingService::class);
        $result = $service->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        // Check journal entries
        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        // Should have 3 entries: debit inventory, debit PPN, credit AP
        $this->assertCount(3, $entries);

        // Debit inventory
        $inventoryEntry = $entries->where('debit', 100000)->first();
        $this->assertNotNull($inventoryEntry);
        $this->assertEquals('1140.01', $inventoryEntry->coa->code);
        $this->assertTrue(strpos($inventoryEntry->description, 'inventory') !== false);

        // Debit PPN
        $ppnEntry = $entries->where('debit', 11000)->first();
        $this->assertNotNull($ppnEntry);
        $this->assertEquals('1170.06', $ppnEntry->coa->code);

        // Credit AP
        $apEntry = $entries->where('credit', 111000)->first();
        $this->assertNotNull($apEntry);
        $this->assertEquals('2110', $apEntry->coa->code);
    }

    /** @test */
    public function post_invoice_with_receipt_debits_unbilled_purchase()
    {
        // Create supplier
        $supplier = Supplier::factory()->create();

        // Create product
        $product = Product::factory()->create([
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);

        // Create PO
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

        // Create receipt for the PO
        $cabang = \App\Models\Cabang::first();
        $receipt = \App\Models\PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => $cabang->id,
        ]);

        // Create invoice from PO (with receipt)
        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\Models\PurchaseOrder',
            'from_model_id' => $po->id,
            'subtotal' => 100000,
            'tax' => 11000,
            'total' => 111000,
            'ppn_rate' => 11,
        ]);

        // Post invoice
        $service = app(LedgerPostingService::class);
        $result = $service->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        // Check journal entries
        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        // Should have 3 entries: debit unbilled purchase, debit PPN, credit AP
        $this->assertCount(3, $entries);

        // Debit unbilled purchase
        $unbilledEntry = $entries->where('debit', 100000)->first();
        $this->assertNotNull($unbilledEntry);
        $this->assertEquals('2100.10', $unbilledEntry->coa->code);
        $this->assertTrue(strpos($unbilledEntry->description, 'unbilled purchase') !== false);

        // Debit PPN
        $ppnEntry = $entries->where('debit', 11000)->first();
        $this->assertNotNull($ppnEntry);
        $this->assertEquals('1170.06', $ppnEntry->coa->code);

        // Credit AP
        $apEntry = $entries->where('credit', 111000)->first();
        $this->assertNotNull($apEntry);
        $this->assertEquals('2110', $apEntry->coa->code);
    }
}