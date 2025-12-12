<?php

namespace Tests\Unit\Observers;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\StockTransferItemObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTransferItemObserverTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $fromWarehouse;
    protected $toWarehouse;
    protected $fromRak;
    protected $toRak;
    protected $product;
    protected $stockTransfer;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the observer for testing
        StockTransferItem::observe(StockTransferItemObserver::class);

        // Create test user
        $this->user = User::factory()->create();

        // Create test data
        $this->cabang = Cabang::factory()->create();

        // Create warehouses
        $this->fromWarehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'From Warehouse'
        ]);
        $this->toWarehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'To Warehouse'
        ]);

        // Create racks
        $this->fromRak = Rak::factory()->create([
            'warehouse_id' => $this->fromWarehouse->id,
            'name' => 'From Rack A1'
        ]);
        $this->toRak = Rak::factory()->create([
            'warehouse_id' => $this->toWarehouse->id,
            'name' => 'To Rack B1'
        ]);

        // Create product
        $category = ProductCategory::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_category_id' => $category->id,
        ]);

        // Create initial inventory stock in source warehouse
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
            'qty_min' => 10,
        ]);

        // Create stock transfer
        $this->stockTransfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Approved',
        ]);
    }

    #[Test]
    public function it_creates_stock_movements_when_stock_transfer_item_is_created()
    {
        // Create stock transfer item
        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Assert that stock movements were created
        $this->assertDatabaseCount('stock_movements', 2);

        // Assert transfer_out movement
        $transferOut = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('rak_id', $this->fromRak->id)
            ->where('type', 'transfer_out')
            ->where('quantity', -10)
            ->first();
        $this->assertNotNull($transferOut);
        $this->assertEquals(StockTransfer::class, $transferOut->from_model_type);
        $this->assertEquals($this->stockTransfer->id, $transferOut->from_model_id);
        $this->assertEquals($this->stockTransfer->transfer_number, $transferOut->reference_id);

        // Assert transfer_in movement
        $transferIn = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('rak_id', $this->toRak->id)
            ->where('type', 'transfer_in')
            ->where('quantity', 10)
            ->first();
        $this->assertNotNull($transferIn);
        $this->assertEquals(StockTransfer::class, $transferIn->from_model_type);
        $this->assertEquals($this->stockTransfer->id, $transferIn->from_model_id);
        $this->assertEquals($this->stockTransfer->transfer_number, $transferIn->reference_id);
    }

    #[Test]
    public function it_updates_inventory_stocks_when_stock_transfer_item_is_created()
    {
        // Check initial stock levels
        $initialSourceStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('rak_id', $this->fromRak->id)
            ->first();
        $this->assertEquals(100, $initialSourceStock->qty_available);

        $initialDestinationStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('rak_id', $this->toRak->id)
            ->first();
        $this->assertNull($initialDestinationStock);

        // Log initial state
        Log::info("Before creating item, source stock qty: " . $initialSourceStock->fresh()->qty_available);

        // Create stock transfer item
        StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Assert source warehouse stock decreased
        $sourceStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('rak_id', $this->fromRak->id)
            ->first();
        $this->assertEquals(90, $sourceStock->qty_available);

        // Assert destination warehouse stock increased
        $destinationStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('rak_id', $this->toRak->id)
            ->first();
        $this->assertNotNull($destinationStock);
        $this->assertEquals(10, $destinationStock->qty_available);
    }

    #[Test]
    public function it_updates_stock_movements_when_stock_transfer_item_is_updated()
    {
        // Create initial stock transfer item
        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Update quantity
        $transferItem->update(['quantity' => 20]);

        // Assert that stock movements were updated
        $transferOut = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('type', 'transfer_out')
            ->first();
        $this->assertEquals(-20, $transferOut->quantity);

        $transferIn = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('type', 'transfer_in')
            ->first();
        $this->assertEquals(20, $transferIn->quantity);
    }

    #[Test]
    public function it_updates_inventory_stocks_when_stock_transfer_item_is_updated()
    {
        // Create initial stock transfer item
        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Update quantity from 10 to 20
        $transferItem->update(['quantity' => 20]);

        // Assert source warehouse stock decreased by additional 10
        $sourceStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('rak_id', $this->fromRak->id)
            ->first();
        $this->assertEquals(80, $sourceStock->qty_available); // 100 - 20

        // Assert destination warehouse stock increased by additional 10
        $destinationStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('rak_id', $this->toRak->id)
            ->first();
        $this->assertEquals(20, $destinationStock->qty_available);
    }

    #[Test]
    public function it_deletes_stock_movements_when_stock_transfer_item_is_deleted()
    {
        // Create stock transfer item
        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Verify stock movements exist
        $this->assertDatabaseCount('stock_movements', 2);

        // Delete the transfer item
        $transferItem->delete();

        // Assert stock movements were soft deleted (still exist but marked as deleted)
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertEquals(2, StockMovement::onlyTrashed()->count());
    }

    #[Test]
    public function it_reverses_inventory_stocks_when_stock_transfer_item_is_deleted()
    {
        // Create stock transfer item
        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Delete the transfer item
        $transferItem->delete();

        // Assert source warehouse stock restored
        $sourceStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('rak_id', $this->fromRak->id)
            ->first();
        $this->assertEquals(100, $sourceStock->qty_available);

        // Assert destination warehouse stock restored to 0
        $destinationStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('rak_id', $this->toRak->id)
            ->first();
        $this->assertEquals(0, $destinationStock->qty_available);
    }

    #[Test]
    public function it_recreates_stock_movements_when_stock_transfer_item_is_restored()
    {
        // Create and delete stock transfer item
        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);
        $transferItem->delete();

        // Verify stock movements were soft deleted (still exist but marked as deleted)
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertEquals(2, StockMovement::onlyTrashed()->count());

        // Restore the transfer item
        $transferItem->restore();

        // Assert stock movements were recreated (now 4 total: 2 soft deleted + 2 new)
        $this->assertDatabaseCount('stock_movements', 4);
        $this->assertEquals(2, StockMovement::onlyTrashed()->count()); // Still 2 soft deleted
        $this->assertEquals(2, StockMovement::count()); // 2 active
    }

    #[Test]
    public function it_force_deletes_stock_movements_when_stock_transfer_item_is_force_deleted()
    {
        // Create stock transfer item
        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Force delete the transfer item
        $transferItem->forceDelete();

        // Assert stock movements were force deleted
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[Test]
    public function it_handles_multiple_stock_transfer_items_correctly()
    {
        // Create multiple products
        $category = ProductCategory::factory()->create(['cabang_id' => $this->cabang->id]);
        $product2 = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_category_id' => $category->id,
        ]);

        // Create initial inventory for second product
        InventoryStock::factory()->create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'qty_available' => 50,
            'qty_reserved' => 0,
            'qty_min' => 5,
        ]);

        // Create multiple stock transfer items
        StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        StockTransferItem::factory()->create([
            'stock_transfer_id' => $this->stockTransfer->id,
            'product_id' => $product2->id,
            'quantity' => 5,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Assert correct number of stock movements created
        $this->assertDatabaseCount('stock_movements', 4); // 2 movements per item

        // Assert inventory adjustments
        $sourceStock1 = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->first();
        $this->assertEquals(90, $sourceStock1->qty_available);

        $sourceStock2 = InventoryStock::where('product_id', $product2->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->first();
        $this->assertEquals(45, $sourceStock2->qty_available);
    }
}