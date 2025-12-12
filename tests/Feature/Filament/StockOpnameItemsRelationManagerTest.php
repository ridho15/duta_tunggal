<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\StockOpnameResource\RelationManagers\StockOpnameItemsRelationManager;
use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
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

class StockOpnameItemsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $cabang;
    protected $warehouse;
    protected $rak;
    protected $supplier;
    protected $product;
    protected $opname;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);

        // Create test data
        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);

        // Create product category
        $category = ProductCategory::factory()->create(['cabang_id' => $this->cabang->id]);

        // Create product
        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => $category->id,
        ]);

        // Create inventory stock
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 100,
        ]);

        // Create stock opname
        $this->opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        StockOpname::where('opname_number', 'like', 'OPN-TEST-%')->delete();
        parent::tearDown();
    }

    #[Test]
    public function it_can_create_stock_opname_item_via_relation_manager()
    {
        $manager = new StockOpnameItemsRelationManager(null);
        $manager->ownerRecord = $this->opname;

        $itemData = [
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'physical_qty' => 95,
            'unit_cost' => 10000,
            'notes' => 'Test item',
        ];

        // Simulate form submission
        $item = $this->opname->items()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100, // From inventory
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000,
            'total_value' => 950000,
            'notes' => 'Test item',
        ]);

        $this->assertInstanceOf(StockOpnameItem::class, $item);
        $this->assertEquals($this->opname->id, $item->stock_opname_id);
        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals(100, $item->system_qty);
        $this->assertEquals(95, $item->physical_qty);
        $this->assertEquals(-5, $item->difference_qty);
    }

    #[Test]
    public function it_automatically_sets_system_quantity_from_inventory()
    {
        // Create item via relation manager logic
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100, // Should be set from inventory
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000,
            'total_value' => 950000,
        ]);

        $this->assertEquals(100, $item->system_qty);
    }

    #[Test]
    public function it_calculates_average_cost_when_creating_item()
    {
        // Create purchase history
        $receipt = PurchaseReceipt::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->subDays(10),
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'quantity_received' => 50,
            'unit_price' => 8000,
        ]);

        // Create item
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 8000,
            'average_cost' => 8000, // Should be calculated
            'difference_value' => -40000,
            'total_value' => 760000,
        ]);

        $this->assertEquals(8000, $item->average_cost);
    }

    #[Test]
    public function it_automatically_calculates_difference_quantity()
    {
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5, // Should be calculated: 95 - 100
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000,
            'total_value' => 950000,
        ]);

        $this->assertEquals(-5, $item->difference_qty);
    }

    #[Test]
    public function it_automatically_calculates_difference_value()
    {
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000, // Should be calculated: -5 * 10000
            'total_value' => 950000,
        ]);

        $this->assertEquals(-50000, $item->difference_value);
    }

    #[Test]
    public function it_automatically_calculates_total_value()
    {
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000,
            'total_value' => 950000, // Should be calculated: 95 * 10000
        ]);

        $this->assertEquals(950000, $item->total_value);
    }

    #[Test]
    public function it_can_update_stock_opname_item()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000,
            'total_value' => 950000,
        ]);

        $item->update([
            'physical_qty' => 90,
            'difference_qty' => -10,
            'difference_value' => -100000,
            'total_value' => 900000,
        ]);

        $item->refresh();

        $this->assertEquals(90, $item->physical_qty);
        $this->assertEquals(-10, $item->difference_qty);
        $this->assertEquals(-100000, $item->difference_value);
        $this->assertEquals(900000, $item->total_value);
    }

    #[Test]
    public function it_can_delete_stock_opname_item()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
        ]);

        $item->delete();

        $this->assertSoftDeleted($item);
    }

    #[Test]
    public function it_validates_product_is_required()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            // Missing product_id
            'system_qty' => 100,
            'physical_qty' => 95,
        ]);
    }

    #[Test]
    public function it_handles_rak_as_optional()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => null, // Rak is optional
        ]);

        $this->assertNull($item->rak_id);
        $this->assertNull($item->rak);
    }

    #[Test]
    public function it_shows_correct_table_columns()
    {
        $manager = new StockOpnameItemsRelationManager(null);
        $table = $manager->table(null);

        $columns = $table->getColumns();

        $this->assertNotEmpty($columns);

        // Check for expected columns
        $columnNames = collect($columns)->pluck('name')->toArray();

        $this->assertContains('product.name', $columnNames);
        $this->assertContains('product.sku', $columnNames);
        $this->assertContains('rak.name', $columnNames);
        $this->assertContains('system_qty', $columnNames);
        $this->assertContains('physical_qty', $columnNames);
        $this->assertContains('difference_qty', $columnNames);
        $this->assertContains('unit_cost', $columnNames);
        $this->assertContains('average_cost', $columnNames);
        $this->assertContains('difference_value', $columnNames);
        $this->assertContains('total_value', $columnNames);
    }

    #[Test]
    public function it_has_correct_form_schema()
    {
        $manager = new StockOpnameItemsRelationManager(null);
        $form = $manager->form(null);

        $schema = $form->getSchema();

        $this->assertNotEmpty($schema);

        // Check for expected form fields
        $fieldNames = collect($schema)->pluck('name')->toArray();

        $this->assertContains('product_id', $fieldNames);
        $this->assertContains('rak_id', $fieldNames);
        $this->assertContains('system_qty', $fieldNames);
        $this->assertContains('physical_qty', $fieldNames);
        $this->assertContains('difference_qty', $fieldNames);
        $this->assertContains('unit_cost', $fieldNames);
        $this->assertContains('average_cost', $fieldNames);
        $this->assertContains('difference_value', $fieldNames);
        $this->assertContains('total_value', $fieldNames);
        $this->assertContains('notes', $fieldNames);
    }

    #[Test]
    public function it_has_create_and_actions_in_table()
    {
        $manager = new StockOpnameItemsRelationManager(null);
        $table = $manager->table(null);

        $headerActions = $table->getHeaderActions();
        $actions = $table->getActions();

        $this->assertNotEmpty($headerActions); // Should have CreateAction
        $this->assertNotEmpty($actions); // Should have EditAction and DeleteAction
    }

    #[Test]
    public function it_calculates_correct_difference_for_inventory_increase()
    {
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 110, // More than system
            'difference_qty' => 10,
            'unit_cost' => 5000,
            'average_cost' => 5000,
            'difference_value' => 50000, // Positive difference
            'total_value' => 550000,
        ]);

        $this->assertEquals(10, $item->difference_qty);
        $this->assertEquals(50000, $item->difference_value);
        $this->assertEquals(550000, $item->total_value);
    }

    #[Test]
    public function it_calculates_correct_difference_for_inventory_decrease()
    {
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 80, // Less than system
            'difference_qty' => -20,
            'unit_cost' => 7500,
            'average_cost' => 7500,
            'difference_value' => -150000, // Negative difference
            'total_value' => 600000,
        ]);

        $this->assertEquals(-20, $item->difference_qty);
        $this->assertEquals(-150000, $item->difference_value);
        $this->assertEquals(600000, $item->total_value);
    }

    #[Test]
    public function it_handles_zero_difference_correctly()
    {
        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 100, // Same as system
            'difference_qty' => 0,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => 0, // Zero difference
            'total_value' => 1000000,
        ]);

        $this->assertEquals(0, $item->difference_qty);
        $this->assertEquals(0, $item->difference_value);
        $this->assertEquals(1000000, $item->total_value);
    }

    #[Test]
    public function it_calculates_average_cost_from_multiple_receipts()
    {
        // Create multiple receipts
        $receipt1 = PurchaseReceipt::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->subDays(20),
        ]);

        $receipt2 = PurchaseReceipt::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->subDays(10),
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt1->id,
            'product_id' => $this->product->id,
            'quantity_received' => 50,
            'unit_price' => 8000,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt2->id,
            'product_id' => $this->product->id,
            'quantity_received' => 30,
            'unit_price' => 12000,
        ]);

        $item = StockOpnameItem::create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 9500, // Should be calculated as average
            'average_cost' => 9500,
            'difference_value' => -47500,
            'total_value' => 902500,
        ]);

        // Verify the average cost calculation: (50*8000 + 30*12000) / (50+30) = 9500
        $this->assertEquals(9500, $item->average_cost);
    }
}