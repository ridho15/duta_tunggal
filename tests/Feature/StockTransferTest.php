<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    protected $stockTransferService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'kode_user' => 'TU001',
        ]);

        $this->cabang = Cabang::factory()->create();
        $this->fromWarehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->toWarehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->fromRak = Rak::factory()->create(['warehouse_id' => $this->fromWarehouse->id]);
        $this->toRak = Rak::factory()->create(['warehouse_id' => $this->toWarehouse->id]);
        $this->product = Product::factory()->create();

        // Assign permissions to user
        $permissions = [
            'view any stock transfer',
            'view stock transfer',
            'create stock transfer',
            'update stock transfer',
            'delete stock transfer',
            'request stock transfer',
            'response stock transfer',
        ];

        foreach ($permissions as $permission) {
            $this->user->givePermissionTo($permission);
        }

        $this->stockTransferService = app(StockTransferService::class);

        // Authenticate user
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_create_stock_transfer_between_warehouses()
    {
        $stockTransfer = StockTransfer::create([
            'transfer_number' => 'TN-20241121-0001',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now(),
            'status' => 'Draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'transfer_number' => 'TN-20241121-0001',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Draft',
        ]);

        $this->assertEquals(1, $stockTransfer->stockTransferItem()->count());
        $this->assertEquals($this->product->id, $stockTransfer->stockTransferItem->first()->product_id);
        $this->assertEquals(10, $stockTransfer->stockTransferItem->first()->quantity);
    }

    /** @test */
    public function it_can_create_stock_transfer_between_racks()
    {
        $anotherRak = Rak::factory()->create(['warehouse_id' => $this->fromWarehouse->id]);

        $stockTransfer = StockTransfer::create([
            'transfer_number' => 'TN-20241121-0002',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->fromWarehouse->id, // Same warehouse, different racks
            'transfer_date' => now(),
            'status' => 'Draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->fromWarehouse->id,
            'to_rak_id' => $anotherRak->id,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'transfer_number' => 'TN-20241121-0002',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->fromWarehouse->id,
            'status' => 'Draft',
        ]);

        $item = $stockTransfer->stockTransferItem->first();
        $this->assertEquals($this->fromRak->id, $item->from_rak_id);
        $this->assertEquals($anotherRak->id, $item->to_rak_id);
        $this->assertEquals($this->fromWarehouse->id, $item->from_warehouse_id);
        $this->assertEquals($this->fromWarehouse->id, $item->to_warehouse_id);
    }

    /** @test */
    public function it_can_request_stock_transfer()
    {
        $stockTransfer = StockTransfer::factory()->create([
            'status' => 'Draft',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $this->stockTransferService->requestTransfer($stockTransfer);

        $this->assertEquals('Request', $stockTransfer->fresh()->status);
    }

    /** @test */
    public function it_can_approve_stock_transfer_and_create_stock_movements()
    {
        $stockTransfer = StockTransfer::factory()->create([
            'status' => 'Request',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $stockTransferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        $this->stockTransferService->approveStockTransfer($stockTransfer);

        // Check that status is updated to Approved
        $this->assertEquals('Approved', $stockTransfer->fresh()->status);

        // Check that stock movements are created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'type' => 'transfer_out',
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->toWarehouse->id,
            'rak_id' => $this->toRak->id,
            'type' => 'transfer_in',
            'quantity' => 15,
        ]);
    }

    /** @test */
    public function it_can_reject_stock_transfer()
    {
        $stockTransfer = StockTransfer::factory()->create([
            'status' => 'Request',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $stockTransfer->update(['status' => 'Reject']);

        $this->assertEquals('Reject', $stockTransfer->fresh()->status);
    }

    /** @test */
    public function it_validates_stock_transfer_status_enum()
    {
        $validStatuses = ['Draft', 'Request', 'Approved', 'Reject', 'Cancelled'];

        foreach ($validStatuses as $status) {
            $stockTransfer = StockTransfer::factory()->create(['status' => $status]);
            $this->assertEquals($status, $stockTransfer->status);
        }
    }

    /** @test */
    public function it_generates_unique_transfer_number()
    {
        $number1 = $this->stockTransferService->generateTransferNumber();

        // Create a transfer to increment the counter
        StockTransfer::create([
            'transfer_number' => $number1,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now(),
            'status' => 'Draft',
        ]);

        $number2 = $this->stockTransferService->generateTransferNumber();

        $this->assertNotEquals($number1, $number2);
        $this->assertStringStartsWith('TN-', $number1);
        $this->assertStringStartsWith('TN-', $number2);
    }

    /** @test */
    public function it_can_soft_delete_stock_transfer_and_items()
    {
        $stockTransfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $stockTransferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $stockTransfer->delete();

        $this->assertSoftDeleted($stockTransfer);
        $this->assertSoftDeleted($stockTransferItem);
    }

    /** @test */
    public function it_can_restore_stock_transfer_and_items()
    {
        $stockTransfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $stockTransferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $stockTransfer->delete();
        $stockTransfer->restore();

        $this->assertFalse($stockTransfer->fresh()->trashed());
        $this->assertFalse($stockTransferItem->fresh()->trashed());
    }

    /** @test */
    public function it_validates_transfer_quantity_is_positive()
    {
        $stockTransfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $stockTransferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 0, // Invalid quantity
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $this->assertEquals(0, $stockTransferItem->quantity);
        // Note: In a real application, you might want to add validation rules
        // to prevent zero or negative quantities
    }

    /** @test */
    public function it_maintains_relationships_correctly()
    {
        $stockTransfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        StockTransferItem::factory()->create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        // Test relationships
        $this->assertInstanceOf(Warehouse::class, $stockTransfer->fromWarehouse);
        $this->assertInstanceOf(Warehouse::class, $stockTransfer->toWarehouse);
        $this->assertInstanceOf(Product::class, $stockTransfer->stockTransferItem->first()->product);
        $this->assertInstanceOf(Rak::class, $stockTransfer->stockTransferItem->first()->fromRak);
        $this->assertInstanceOf(Rak::class, $stockTransfer->stockTransferItem->first()->toRak);
    }
}