<?php

namespace Tests\Unit\Observers;

use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurchaseOrderObserverTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $supplier;
    protected $warehouse;
    protected $currency;
    protected $product;
    protected $assetCoa;
    protected $payableCoa;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test data
        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->currency = Currency::factory()->create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1
        ]);

        // Create product category
        $category = ProductCategory::factory()->create(['cabang_id' => $this->cabang->id]);

        // Create product
        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => $category->id,
        ]);

        // Create COAs needed for asset acquisition
        $this->assetCoa = ChartOfAccount::factory()->create([
            'code' => '1500',
            'name' => 'HARGA PEROLEHAN ASET TETAP',
            'type' => 'Asset'
        ]);

        $this->payableCoa = ChartOfAccount::factory()->create([
            'code' => '2110',
            'name' => 'HUTANG USAHA',
            'type' => 'Liability'
        ]);

        // Create COAs needed for asset creation
        ChartOfAccount::factory()->create([
            'code' => '1210.01',
            'name' => 'PERALATAN KANTOR',
            'type' => 'Asset'
        ]);

        ChartOfAccount::factory()->create([
            'code' => '1220.01',
            'name' => 'AKUMULASI PENYUSUTAN',
            'type' => 'Asset'
        ]);

        ChartOfAccount::factory()->create([
            'code' => '6311',
            'name' => 'BEBAN PENYUSUTAN',
            'type' => 'Expense'
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        PurchaseOrder::where('po_number', 'like', 'TEST-PO-ASSET-%')->delete();
        Asset::where('code', 'like', 'AST-%')->delete();
        JournalEntry::where('reference', 'like', 'PO-TEST-PO-ASSET-%')->delete();

        parent::tearDown();
    }

    #[Test]
    public function it_creates_asset_records_when_purchase_order_is_approved()
    {
        $this->actingAs($this->user);

        // Create purchase order with is_asset = true
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'TEST-PO-ASSET-' . now()->format('YmdHis'),
            'order_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'is_asset' => true,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Create purchase order item
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // 2 units of asset
            'unit_price' => 1000000, // 1 million per unit
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Inklusif',
        ]);

        // Verify no assets exist before approval
        $this->assertDatabaseMissing('assets', [
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        // Approve the purchase order
        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
        ]);

        // Verify assets were created
        $this->assertDatabaseHas('assets', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'purchase_cost' => 1000000,
            'status' => 'active',
            'cabang_id' => $this->cabang->id,
        ]);

        // Verify 2 assets were created (one per unit)
        $assets = Asset::where('purchase_order_id', $purchaseOrder->id)->get();
        $this->assertCount(2, $assets);

        // Verify PO status changed to completed
        $purchaseOrder->refresh();
        $this->assertEquals('completed', $purchaseOrder->status);
    }

    #[Test]
    public function it_creates_journal_entries_automatically_when_asset_purchase_order_is_approved()
    {
        $this->actingAs($this->user);

        // Create purchase order with is_asset = true
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'TEST-PO-ASSET-' . now()->format('YmdHis'),
            'order_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'is_asset' => true,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Create purchase order item
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2000000, // 2 million
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Inklusif',
        ]);

        // Calculate expected total
        $expectedTotal = 2000000; // 1 unit * 2,000,000

        // Verify no journal entries exist before approval
        $this->assertDatabaseMissing('journal_entries', [
            'reference' => 'PO-' . $purchaseOrder->po_number,
        ]);

        // Approve the purchase order
        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
        ]);

        // Verify journal entries were created
        $journalEntries = JournalEntry::where('reference', 'PO-' . $purchaseOrder->po_number)->get();
        $this->assertCount(2, $journalEntries, 'Should create 2 journal entries (debit and credit)');

        // Verify debit entry (Fixed Asset)
        $debitEntry = $journalEntries->where('debit', '>', 0)->first();
        $this->assertNotNull($debitEntry, 'Debit entry should exist');
        $this->assertEquals($this->assetCoa->id, $debitEntry->coa_id);
        $this->assertEquals($expectedTotal, $debitEntry->debit);
        $this->assertEquals(0, $debitEntry->credit);
        $this->assertEquals('Asset acquisition from PO ' . $purchaseOrder->po_number, $debitEntry->description);
        $this->assertEquals('asset_acquisition', $debitEntry->journal_type);
        $this->assertEquals(PurchaseOrder::class, $debitEntry->source_type);
        $this->assertEquals($purchaseOrder->id, $debitEntry->source_id);

        // Verify credit entry (Accounts Payable)
        $creditEntry = $journalEntries->where('credit', '>', 0)->first();
        $this->assertNotNull($creditEntry, 'Credit entry should exist');
        $this->assertEquals($this->payableCoa->id, $creditEntry->coa_id);
        $this->assertEquals(0, $creditEntry->debit);
        $this->assertEquals($expectedTotal, $creditEntry->credit);
        $this->assertEquals('Accounts payable for asset acquisition PO ' . $purchaseOrder->po_number, $creditEntry->description);
        $this->assertEquals('asset_acquisition', $creditEntry->journal_type);
        $this->assertEquals(PurchaseOrder::class, $creditEntry->source_type);
        $this->assertEquals($purchaseOrder->id, $creditEntry->source_id);

        // Verify journal entries balance (total debit = total credit)
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit, 'Journal entries should balance');
        $this->assertEquals($expectedTotal, $totalDebit, 'Total debit should equal asset cost');
        $this->assertEquals($expectedTotal, $totalCredit, 'Total credit should equal asset cost');
    }

    #[Test]
    public function it_handles_multiple_asset_items_correctly()
    {
        $this->actingAs($this->user);

        // Create purchase order with is_asset = true
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'TEST-PO-ASSET-' . now()->format('YmdHis'),
            'order_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'is_asset' => true,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Create multiple purchase order items
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // 2 laptops
            'unit_price' => 5000000, // 5 million each
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Inklusif',
        ]);

        // Create another product for variety
        $product2 = Product::factory()->create([
            'name' => 'Server Equipment',
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => ProductCategory::factory()->create(['cabang_id' => $this->cabang->id])->id,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product2->id,
            'quantity' => 1, // 1 server
            'unit_price' => 15000000, // 15 million
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Inklusif',
        ]);

        // Expected totals: (2 * 5,000,000) + (1 * 15,000,000) = 25,000,000
        $expectedTotal = 25000000;

        // Approve the purchase order
        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
        ]);

        // Verify assets were created (2 laptops + 1 server = 3 assets)
        $assets = Asset::where('purchase_order_id', $purchaseOrder->id)->get();
        $this->assertCount(3, $assets);

        // Verify journal entries total matches expected amount
        $journalEntries = JournalEntry::where('reference', 'PO-' . $purchaseOrder->po_number)->get();
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');

        $this->assertEquals($expectedTotal, $totalDebit);
        $this->assertEquals($expectedTotal, $totalCredit);
    }

    #[Test]
    public function it_prevents_duplicate_asset_creation_on_reapproval()
    {
        $this->actingAs($this->user);

        // Create and approve purchase order
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'TEST-PO-ASSET-' . now()->format('YmdHis'),
            'order_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'is_asset' => true,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Inklusif',
        ]);

        // First approval
        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
        ]);

        $initialAssetCount = Asset::where('purchase_order_id', $purchaseOrder->id)->count();
        $initialJournalCount = JournalEntry::where('reference', 'PO-' . $purchaseOrder->po_number)->count();

        // Try to approve again (should not create duplicates)
        $purchaseOrder->update([
            'status' => 'approved', // Same status
            'approved_by' => $this->user->id,
        ]);

        // Verify no additional assets or journals were created
        $this->assertEquals($initialAssetCount, Asset::where('purchase_order_id', $purchaseOrder->id)->count());
        $this->assertEquals($initialJournalCount, JournalEntry::where('reference', 'PO-' . $purchaseOrder->po_number)->count());
    }

    #[Test]
    public function it_does_not_create_journal_entries_for_non_asset_purchase_orders()
    {
        $this->actingAs($this->user);

        // Create purchase order with is_asset = false
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'TEST-PO-NON-ASSET-' . now()->format('YmdHis'),
            'order_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'is_asset' => false, // Not an asset purchase
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Inklusif',
        ]);

        // Approve the purchase order
        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
        ]);

        // Verify no asset acquisition journal entries were created
        $assetAcquisitionEntries = JournalEntry::where('reference', 'PO-' . $purchaseOrder->po_number)
            ->where('journal_type', 'asset_acquisition')
            ->count();

        $this->assertEquals(0, $assetAcquisitionEntries, 'Should not create asset acquisition journal entries for non-asset POs');

        // Verify no assets were created
        $this->assertEquals(0, Asset::where('purchase_order_id', $purchaseOrder->id)->count());
    }
}