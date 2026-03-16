<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockReservation;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TC-SR-001..006 — Stock Reservation Service Feature Tests
 *
 * Covers: reserve/release via MaterialIssue, InsufficientStockException,
 * concurrent lock guard, SO-cancel release, and consumption flow.
 */
class StockReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockReservationService $stockService;
    private SalesOrderService $soService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockReservationService();
        $this->soService    = new SalesOrderService();
    }

    // -------------------------------------------------------------------------
    // Helper: create a complete MaterialIssue setup with one item.
    // Returns ['materialIssue', 'inventoryStock', 'product', 'warehouse'].
    // -------------------------------------------------------------------------
    private function makeMaterialIssueWithStock(int $initialAvailable = 100, int $initialReserved = 0, int $itemQty = 10): array
    {
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create();
        $rak       = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
        $uom       = UnitOfMeasure::factory()->create();

        $inventoryStock = InventoryStock::create([
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'rak_id'        => $rak->id,
            'qty_available' => $initialAvailable,
            'qty_reserved'  => $initialReserved,
            'qty_min'       => 0,
        ]);

        $materialIssue = MaterialIssue::factory()->create([
            'status'       => MaterialIssue::STATUS_APPROVED,
            'warehouse_id' => $warehouse->id,
        ]);

        MaterialIssueItem::factory()->create([
            'material_issue_id' => $materialIssue->id,
            'product_id'        => $product->id,
            'warehouse_id'      => $warehouse->id,
            'rak_id'            => $rak->id,
            'uom_id'            => $uom->id,
            'quantity'          => $itemQty,
            'cost_per_unit'     => 5_000,
            'total_cost'        => $itemQty * 5_000,
        ]);

        $materialIssue->load('items');

        return compact('materialIssue', 'inventoryStock', 'product', 'warehouse');
    }

    // -------------------------------------------------------------------------
    // TC-SR-001: reserveStockForMaterialIssue() increments qty_reserved
    //            and decrements qty_available.
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_sr_001_reserve_stock_increases_reserved_and_decreases_available(): void
    {
        ['materialIssue' => $mi, 'inventoryStock' => $stock] =
            $this->makeMaterialIssueWithStock(initialAvailable: 100, initialReserved: 0, itemQty: 10);

        $this->stockService->reserveStockForMaterialIssue($mi);

        $stock->refresh();
        $this->assertEquals(10, $stock->qty_reserved);
        $this->assertEquals(90, $stock->qty_available);

        $this->assertDatabaseHas('stock_reservations', [
            'material_issue_id' => $mi->id,
            'product_id'        => $mi->items->first()->product_id,
            'quantity'          => 10,
        ]);
    }

    // -------------------------------------------------------------------------
    // TC-SR-002: releaseStockReservationsForMaterialIssue() decrements qty_reserved
    //            and restores qty_available.
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_sr_002_release_reservation_decreases_reserved_and_restores_available(): void
    {
        ['materialIssue' => $mi, 'inventoryStock' => $stock] =
            $this->makeMaterialIssueWithStock(initialAvailable: 100, initialReserved: 0, itemQty: 10);

        // First reserve
        $this->stockService->reserveStockForMaterialIssue($mi);
        $stock->refresh();
        $this->assertEquals(10, $stock->qty_reserved);
        $this->assertEquals(90, $stock->qty_available);

        // Then release
        $this->stockService->releaseStockReservationsForMaterialIssue($mi);

        $stock->refresh();
        $this->assertEquals(0, $stock->qty_reserved);
        $this->assertEquals(100, $stock->qty_available);

        $this->assertDatabaseMissing('stock_reservations', [
            'material_issue_id' => $mi->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // TC-SR-003: Confirming a SaleOrder with insufficient stock throws
    //            InsufficientStockException (guard in SalesOrderService::confirm).
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_sr_003_over_reservation_throws_insufficient_stock_exception(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create();

        // Only 5 units available
        InventoryStock::create([
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'qty_available' => 5,
            'qty_reserved'  => 0,
            'qty_min'       => 0,
        ]);

        $saleOrder = SaleOrder::factory()->create(['status' => 'draft']);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'quantity'      => 20, // requesting more than available
        ]);
        $saleOrder->load('saleOrderItem');

        $this->expectException(InsufficientStockException::class);

        $this->soService->confirm($saleOrder);
    }

    // -------------------------------------------------------------------------
    // TC-SR-004: SalesOrderService::confirm() uses lockForUpdate() so that
    //            concurrent reservations cannot produce negative qty.
    //            Verified: a second confirmation attempt on exhausted stock
    //            still throws InsufficientStockException (no negative stock).
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_sr_004_concurrent_reservation_never_produces_negative_available(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create();

        $stock = InventoryStock::create([
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'qty_available' => 10,
            'qty_reserved'  => 0,
            'qty_min'       => 0,
        ]);

        // First SO — reserves exactly 10
        $so1 = SaleOrder::factory()->create(['status' => 'draft']);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so1->id,
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'quantity'      => 10,
        ]);
        $so1->load('saleOrderItem');
        $this->soService->confirm($so1);

        $stock->refresh();
        $this->assertEquals(0, $stock->qty_available, 'All stock should be reserved after first confirm');

        // Second SO — tries to reserve 5 more → should throw
        $so2 = SaleOrder::factory()->create(['status' => 'draft']);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $so2->id,
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'quantity'      => 5,
        ]);
        $so2->load('saleOrderItem');

        $this->expectException(InsufficientStockException::class);
        $this->soService->confirm($so2);

        // Stock must NOT go negative
        $stock->refresh();
        $this->assertGreaterThanOrEqual(0, $stock->qty_available);
    }

    // -------------------------------------------------------------------------
    // TC-SR-005: Cancelling an SO releases its stock reservations and restores
    //            qty_available (SalesOrderService::cancel).
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_sr_005_cancel_sale_order_releases_stock_reservations(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product   = Product::factory()->create();

        $stock = InventoryStock::create([
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'qty_available' => 50,
            'qty_reserved'  => 0,
            'qty_min'       => 0,
        ]);

        $saleOrder = SaleOrder::factory()->create(['status' => 'draft']);
        SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id'    => $product->id,
            'warehouse_id'  => $warehouse->id,
            'quantity'      => 15,
        ]);
        $saleOrder->load('saleOrderItem');

        // Confirm → reserve 15 units
        $this->soService->confirm($saleOrder);
        $stock->refresh();
        $this->assertEquals(15, $stock->qty_reserved);
        $this->assertEquals(35, $stock->qty_available);

        // Cancel → release reservations
        $saleOrder->refresh();
        $this->soService->cancel($saleOrder);

        $stock->refresh();
        $this->assertEquals(0, $stock->qty_reserved,  'qty_reserved should be 0 after SO cancel');
        $this->assertEquals(50, $stock->qty_available, 'qty_available should be restored after SO cancel');

        $this->assertDatabaseMissing('stock_reservations', [
            'sale_order_id' => $saleOrder->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // TC-SR-006: consumeReservedStockForMaterialIssue() permanently consumes
    //            stock: qty_reserved decreases back to 0, qty_available stays
    //            at the lower value (stock is genuinely used up).
    // -------------------------------------------------------------------------

    /** @test */
    public function tc_sr_006_consume_reservation_decreases_reserved_and_leaves_available_low(): void
    {
        ['materialIssue' => $mi, 'inventoryStock' => $stock] =
            $this->makeMaterialIssueWithStock(initialAvailable: 100, initialReserved: 0, itemQty: 20);

        // Reserve 20 units → available drops to 80, reserved becomes 20
        $this->stockService->reserveStockForMaterialIssue($mi);
        $stock->refresh();
        $this->assertEquals(20, $stock->qty_reserved);
        $this->assertEquals(80, $stock->qty_available);

        // Mark material issue as completed so consume can proceed
        $mi->update(['status' => MaterialIssue::STATUS_COMPLETED]);
        $mi->refresh();

        // Consume: qty_reserved → 0, qty_available stays at 80 (permanently consumed)
        $this->stockService->consumeReservedStockForMaterialIssue($mi);

        $stock->refresh();
        $this->assertEquals(0,  $stock->qty_reserved,  'Reserved should be 0 after consumption');
        $this->assertEquals(80, $stock->qty_available,  'Available should remain low — stock is consumed');

        $this->assertDatabaseMissing('stock_reservations', [
            'material_issue_id' => $mi->id,
        ]);
    }
}
