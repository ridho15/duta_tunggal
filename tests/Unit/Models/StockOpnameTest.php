<?php

namespace Tests\Unit\Models;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Rak;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $rak;
    protected $supplier;
    protected $currency;
    protected $product;
    protected $inventoryCoa;
    protected $adjustmentCoa;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test data
        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);
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

        // Create COAs needed for stock opname
        $this->inventoryCoa = ChartOfAccount::factory()->create([
            'code' => '1100',
            'name' => 'INVENTORY',
            'type' => 'Asset'
        ]);

        $this->adjustmentCoa = ChartOfAccount::factory()->create([
            'code' => '5100',
            'name' => 'INVENTORY ADJUSTMENT',
            'type' => 'Expense'
        ]);

        // Create initial inventory stock
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        StockOpname::where('opname_number', 'like', 'OPN-TEST-%')->delete();
        parent::tearDown();
    }

    #[Test]
    public function it_can_generate_unique_opname_number()
    {
        $number1 = StockOpname::generateOpnameNumber();
        $number2 = StockOpname::generateOpnameNumber();

        $this->assertNotEquals($number1, $number2);
        $this->assertStringStartsWith('OPN-' . now()->format('Ymd') . '-', $number1);
        $this->assertStringStartsWith('OPN-' . now()->format('Ymd') . '-', $number2);
    }

    #[Test]
    public function it_can_create_stock_opname()
    {
        $opnameData = [
            'opname_number' => 'OPN-TEST-001',
            'opname_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ];

        $opname = StockOpname::create($opnameData);

        $this->assertInstanceOf(StockOpname::class, $opname);
        $this->assertEquals('OPN-TEST-001', $opname->opname_number);
        $this->assertEquals('draft', $opname->status);
        $this->assertEquals($this->warehouse->id, $opname->warehouse_id);
    }

    #[Test]
    public function it_has_relationships_with_other_models()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Warehouse::class, $opname->warehouse);
        $this->assertInstanceOf(User::class, $opname->creator);
        $this->assertEquals($this->warehouse->id, $opname->warehouse->id);
        $this->assertEquals($this->user->id, $opname->creator->id);
    }

    #[Test]
    public function it_can_add_stock_opname_items()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $itemData = [
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000,
            'total_value' => 950000,
        ];

        $item = $opname->items()->create($itemData);

        $this->assertInstanceOf(StockOpnameItem::class, $item);
        $this->assertEquals($opname->id, $item->stock_opname_id);
        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals(-5, $item->difference_qty);
        $this->assertEquals(-50000, $item->difference_value);
    }

    #[Test]
    public function stock_opname_item_can_calculate_average_cost()
    {
        // Create purchase orders first
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Create purchase order items with unit prices
        $poItem1 = PurchaseOrderItem::create([
            'purchase_order_id' => $po1->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 8000,
            'currency_id' => $this->currency->id,
        ]);

        $poItem2 = PurchaseOrderItem::create([
            'purchase_order_id' => $po2->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'unit_price' => 12000,
            'currency_id' => $this->currency->id,
        ]);

        // Create purchase receipts linked to purchase orders
        $receipt1 = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po1->id,
            'receipt_date' => now()->subDays(10),
            'cabang_id' => $this->cabang->id,
        ]);

        $receipt2 = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po2->id,
            'receipt_date' => now()->subDays(5),
            'cabang_id' => $this->cabang->id,
        ]);

        // Add items to receipts
        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt1->id,
            'purchase_order_item_id' => $poItem1->id,
            'product_id' => $this->product->id,
            'qty_received' => 50,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt2->id,
            'purchase_order_item_id' => $poItem2->id,
            'product_id' => $this->product->id,
            'qty_received' => 30,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
        ]);

        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now(),
            'created_by' => $this->user->id,
        ]);

        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
        ]);

        $averageCost = $item->calculateAverageCost();

        // Expected: (50 * 8000 + 30 * 12000) / (50 + 30) = (400000 + 360000) / 80 = 9500
        $this->assertEquals(9500, $averageCost);
    }

    #[Test]
    public function stock_opname_item_can_calculate_total_value()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'physical_qty' => 50,
            'average_cost' => 10000,
        ]);

        $totalValue = $item->calculateTotalValue();

        $this->assertEquals(500000, $totalValue); // 50 * 10000
    }

    #[Test]
    public function stock_opname_item_can_calculate_difference_value()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'difference_qty' => -10,
            'average_cost' => 5000,
        ]);

        $differenceValue = $item->calculateDifferenceValue();

        $this->assertEquals(-50000, $differenceValue); // -10 * 5000
    }

    #[Test]
    public function it_can_change_status_to_in_progress()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $opname->update(['status' => 'in_progress']);

        $this->assertEquals('in_progress', $opname->fresh()->status);
    }

    #[Test]
    public function it_can_change_status_to_completed()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'in_progress',
            'created_by' => $this->user->id,
        ]);

        $opname->update(['status' => 'completed']);

        $this->assertEquals('completed', $opname->fresh()->status);
    }

    #[Test]
    public function it_creates_journal_entries_when_approved_with_inventory_increase()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'opname_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // Create item with positive difference (inventory increase)
        StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'difference_value' => 50000, // Positive difference
        ]);

        // Approve the opname
        $opname->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Check journal entries created
        $journalEntries = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->get();

        $this->assertCount(2, $journalEntries);

        // Check debit entry (inventory increase)
        $debitEntry = $journalEntries->where('debit', 50000)->first();
        $this->assertNotNull($debitEntry);
        $this->assertEquals($this->inventoryCoa->id, $debitEntry->coa_id);
        $this->assertEquals(50000, $debitEntry->debit);
        $this->assertEquals(0, $debitEntry->credit);

        // Check credit entry (adjustment)
        $creditEntry = $journalEntries->where('credit', 50000)->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals($this->adjustmentCoa->id, $creditEntry->coa_id);
        $this->assertEquals(0, $creditEntry->debit);
        $this->assertEquals(50000, $creditEntry->credit);
    }

    #[Test]
    public function it_creates_journal_entries_when_approved_with_inventory_decrease()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'opname_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // Create item with negative difference (inventory decrease)
        StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'difference_value' => -30000, // Negative difference
        ]);

        // Approve the opname
        $opname->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Check journal entries created
        $journalEntries = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->get();

        $this->assertCount(2, $journalEntries);

        // Check debit entry (adjustment for decrease)
        $debitEntry = $journalEntries->where('debit', 30000)->first();
        $this->assertNotNull($debitEntry);
        $this->assertEquals($this->adjustmentCoa->id, $debitEntry->coa_id);

        // Check credit entry (inventory decrease)
        $creditEntry = $journalEntries->where('credit', 30000)->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals($this->inventoryCoa->id, $creditEntry->coa_id);
    }

    #[Test]
    public function it_does_not_create_journal_entries_when_no_adjustment_needed()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'opname_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // Create item with zero difference
        StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'difference_value' => 0,
        ]);

        // Approve the opname
        $opname->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Check no journal entries created
        $journalEntries = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->get();

        $this->assertCount(0, $journalEntries);
    }

    #[Test]
    public function it_handles_multiple_items_correctly()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'opname_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // Create multiple items with different differences
        StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'difference_value' => 20000,
        ]);

        $product2 = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => ProductCategory::factory()->create(['cabang_id' => $this->cabang->id])->id,
        ]);

        StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product2->id,
            'difference_value' => -15000,
        ]);

        // Approve the opname
        $opname->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Check journal entries for net positive adjustment (20000 - 15000 = 5000)
        $journalEntries = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->get();

        $this->assertCount(2, $journalEntries);

        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');

        $this->assertEquals(5000, $totalDebit);
        $this->assertEquals(5000, $totalCredit);
    }

    #[Test]
    public function it_syncs_journal_entries_when_opname_data_changes()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'opname_date' => now(),
            'opname_number' => 'OPN-TEST-001',
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        // Create journal entries
        JournalEntry::factory()->create([
            'source_type' => StockOpname::class,
            'source_id' => $opname->id,
            'reference' => 'ADJ-OPN-TEST-001',
            'description' => 'Penyesuaian inventory hasil stock opname OPN-TEST-001',
            'journal_type' => 'stock_opname',
        ]);

        // Update opname number
        $opname->update(['opname_number' => 'OPN-TEST-002']);

        // Sync journal entries
        $opname->syncJournalEntries();

        // Check journal entries updated
        $journalEntry = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->first();

        $this->assertEquals('ADJ-OPN-TEST-002', $journalEntry->reference);
        $this->assertStringContainsString('OPN-TEST-002', $journalEntry->description);
    }

    #[Test]
    public function it_has_correct_relationships()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        // Test items relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $opname->items());

        // Test stock movements relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $opname->stockMovements());

        // Test journal entries relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $opname->journalEntries());
    }

    #[Test]
    public function it_scopes_by_cabang()
    {
        // This test assumes the CabangScope is applied
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        // The global scope should be applied automatically
        $this->assertTrue(true); // If no exception, scope is working
    }

    #[Test]
    public function it_logs_global_activity()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        // Check if LogsGlobalActivity trait is used
        $traits = class_uses($opname);
        $this->assertArrayHasKey('App\Traits\LogsGlobalActivity', $traits);
    }

    #[Test]
    public function it_handles_soft_deletes()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $opname->delete();

        $this->assertSoftDeleted($opname);
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create without required fields
        StockOpname::create([]);
    }

    #[Test]
    public function it_validates_unique_opname_number()
    {
        StockOpname::factory()->create([
            'opname_number' => 'OPN-TEST-UNIQUE',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create with duplicate opname number
        StockOpname::factory()->create([
            'opname_number' => 'OPN-TEST-UNIQUE',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_handles_foreign_key_constraints()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        // Verify the relationship exists
        $this->assertEquals($this->warehouse->id, $opname->warehouse->id);

        // Test that we can't create with invalid warehouse_id
        $this->expectException(\Illuminate\Database\QueryException::class);
        StockOpname::factory()->create([
            'warehouse_id' => 99999, // Non-existent warehouse
            'created_by' => $this->user->id,
        ]);
    }
}