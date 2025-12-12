<?php

namespace Tests\Unit\Models;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $fromWarehouse;
    protected $toWarehouse;
    protected $fromRak;
    protected $toRak;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    #[Test]
    public function it_can_create_stock_transfer()
    {
        $transferData = [
            'transfer_number' => 'ST-TEST-001',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
        ];

        $transfer = StockTransfer::create($transferData);

        $this->assertInstanceOf(StockTransfer::class, $transfer);
        $this->assertEquals('ST-TEST-001', $transfer->transfer_number);
        $this->assertEquals('Draft', $transfer->status);
        $this->assertEquals($this->fromWarehouse->id, $transfer->from_warehouse_id);
        $this->assertEquals($this->toWarehouse->id, $transfer->to_warehouse_id);
    }

    #[Test]
    public function it_has_relationships_with_other_models()
    {
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $this->assertInstanceOf(Warehouse::class, $transfer->fromWarehouse);
        $this->assertInstanceOf(Warehouse::class, $transfer->toWarehouse);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $transfer->stockTransferItem);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $transfer->journalEntries);
    }

    #[Test]
    public function it_handles_deleting_event_with_cascading_stock_movement_deletion()
    {
        // Create stock transfer with items
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        // Create transfer items
        $item1 = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Verify stock movements exist
        $this->assertDatabaseCount('stock_movements', 2);

        // Delete the stock transfer
        $transfer->delete();

        // Assert stock movements were deleted
        $this->assertDatabaseCount('stock_movements', 0);

        // Assert transfer items were soft deleted
        $this->assertSoftDeleted($item1);
    }

    #[Test]
    public function it_handles_force_deleting_event_with_force_cascading_deletion()
    {
        // Create stock transfer with items
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        // Create transfer items
        $item1 = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Verify stock movements exist
        $this->assertDatabaseCount('stock_movements', 2);

        // Force delete the stock transfer
        $transfer->forceDelete();

        // Assert stock movements were force deleted
        $this->assertDatabaseCount('stock_movements', 0);

        // Assert transfer items were force deleted
        $this->assertDatabaseMissing('stock_transfer_items', ['id' => $item1->id]);
    }

    #[Test]
    public function it_handles_restoring_event_with_cascading_restoration()
    {
        // Create stock transfer with items
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        $item1 = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Delete the transfer
        $transfer->delete();

        // Verify soft deletion
        $this->assertSoftDeleted($transfer);
        $this->assertSoftDeleted($item1);

        // Restore the transfer
        $transfer->restore();

        // Assert restoration
        $this->assertNotSoftDeleted($transfer);
        $this->assertNotSoftDeleted($item1);
    }

    #[Test]
    public function it_handles_updating_event_for_quantity_changes()
    {
        // Create stock transfer with approved status
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        // Create transfer item
        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Update the transfer item quantity directly (simulating a change)
        $item->update(['quantity' => 20]);

        // The updating event should handle this through the StockTransferItemObserver
        // Verify that stock movements were updated
        $transferOut = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('movement_type', 'transfer_out')
            ->first();
        $this->assertEquals(-20, $transferOut->quantity);

        $transferIn = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('movement_type', 'transfer_in')
            ->first();
        $this->assertEquals(20, $transferIn->quantity);
    }

    #[Test]
    public function it_only_handles_updating_event_for_approved_transfers()
    {
        // Create stock transfer with draft status
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Draft', // Not approved
        ]);

        // Create transfer item
        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Update transfer status to approved
        $transfer->update(['status' => 'Approved']);

        // The updating event should not trigger for status changes
        // but the StockTransferItemObserver should handle the creation
        $this->assertDatabaseCount('stock_movements', 2);
    }

    #[Test]
    public function it_can_generate_unique_transfer_number()
    {
        $number1 = StockTransfer::generateTransferNumber();
        $number2 = StockTransfer::generateTransferNumber();

        $this->assertNotEquals($number1, $number2);
        $this->assertStringStartsWith('ST-' . now()->format('Ymd') . '-', $number1);
        $this->assertStringStartsWith('ST-' . now()->format('Ymd') . '-', $number2);
    }
}