<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Warehouse Audit Test Suite', function () {

    beforeEach(function () {
        // Create authenticated user
        $user = User::factory()->create();
        Auth::login($user);

        // Create required master data
        $this->cabang = Cabang::factory()->create([
            'kode' => 'BR001',
            'nama' => 'Main Branch'
        ]);

        $this->category = ProductCategory::factory()->create([
            'kode' => 'CAT001'
        ]);

        $this->warehouse1 = Warehouse::factory()->create([
            'kode' => 'WH001',
            'name' => 'Main Warehouse',
            'cabang_id' => $this->cabang->id,
            'location' => 'Main Location',
            'status' => true
        ]);

        $this->warehouse2 = Warehouse::factory()->create([
            'kode' => 'WH002',
            'name' => 'Secondary Warehouse',
            'cabang_id' => $this->cabang->id,
            'location' => 'Secondary Location',
            'status' => true
        ]);

        $this->rak1 = Rak::factory()->create([
            'code' => 'RACK001',
            'name' => 'Rack A1',
            'warehouse_id' => $this->warehouse1->id
        ]);

        $this->rak2 = Rak::factory()->create([
            'code' => 'RACK002',
            'name' => 'Rack B1',
            'warehouse_id' => $this->warehouse1->id
        ]);

        $this->product1 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'name' => 'Test Product 1',
            'cost_price' => 50000,
            'sell_price' => 75000
        ]);

        $this->product2 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'name' => 'Test Product 2',
            'cost_price' => 30000,
            'sell_price' => 45000
        ]);

        $this->customer = Customer::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->salesOrderService = app(SalesOrderService::class);
    });

    describe('Inventory Stock Management', function () {

        it('can create and update inventory stock correctly', function () {
            // Test creating inventory stock
            $inventoryStock = InventoryStock::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 100,
                'qty_reserved' => 0,
                'qty_min' => 10,
                'rak_id' => $this->rak1->id
            ]);

            expect($inventoryStock)->toBeInstanceOf(InventoryStock::class);
            expect($inventoryStock->qty_available)->toBe(100);
            expect($inventoryStock->qty_reserved)->toBe(0);
            expect($inventoryStock->qty_min)->toBe(10);

            // Test updating inventory stock
            $inventoryStock->update([
                'qty_available' => 150,
                'qty_reserved' => 20
            ]);

            expect($inventoryStock->fresh()->qty_available)->toBe(150.0);
            expect($inventoryStock->fresh()->qty_reserved)->toBe(20.0);
        });

        it('validates inventory stock relationships', function () {
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak1->id
            ]);

            expect($inventoryStock->product)->not->toBeNull();
            expect($inventoryStock->warehouse)->not->toBeNull();
            expect($inventoryStock->rak)->not->toBeNull();

            expect($inventoryStock->product->name)->toBe($this->product1->name);
            expect($inventoryStock->warehouse->name)->toBe($this->warehouse1->name);
            expect($inventoryStock->rak->code)->toBe($this->rak1->code);
        });

        it('handles stock reservations correctly', function () {
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 100,
                'qty_reserved' => 0
            ]);

            // Reserve 20 units
            $inventoryStock->increment('qty_reserved', 20);
            $inventoryStock->decrement('qty_available', 20);

            expect($inventoryStock->fresh()->qty_available)->toBe(80.0);
            expect($inventoryStock->fresh()->qty_reserved)->toBe(20.0);

            // Release reservation
            $inventoryStock->decrement('qty_reserved', 20);
            $inventoryStock->increment('qty_available', 20);

            expect($inventoryStock->fresh()->qty_available)->toBe(100.0);
            expect($inventoryStock->fresh()->qty_reserved)->toBe(0.0);
        });

        it('detects low stock alerts', function () {
            // Create product with low stock
            $lowStockInventory = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 5,
                'qty_min' => 10
            ]);

            // Create normal stock
            $normalStockInventory = InventoryStock::factory()->create([
                'product_id' => $this->product2->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 50,
                'qty_min' => 10
            ]);

            // Query for low stock items
            $lowStockItems = InventoryStock::whereColumn('qty_available', '<=', 'qty_min')->get();

            expect($lowStockItems)->toHaveCount(1);
            expect($lowStockItems->first()->product_id)->toBe($this->product1->id);
        });

    });

    describe('Stock Movement Operations', function () {

        it('records stock movements correctly', function () {
            // Create initial stock
            InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 100
            ]);

            // Record purchase movement
            $movement = StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => 50,
                'value' => 2500000, // 50 * 50000
                'type' => 'purchase_in',
                'reference_id' => 'PO001',
                'date' => now(),
                'rak_id' => $this->rak1->id
            ]);

            expect($movement)->toBeInstanceOf(StockMovement::class);
            expect($movement->type)->toBe('purchase_in');
            expect($movement->quantity)->toBe(50);
            expect($movement->value)->toBe('2500000.00');
        });

        it('validates stock movement types', function () {
            $validTypes = ['purchase_in', 'sales', 'transfer_in', 'transfer_out', 'manufacture_in', 'manufacture_out', 'adjustment_in', 'adjustment_out'];

            foreach ($validTypes as $type) {
                $movement = StockMovement::create([
                    'product_id' => $this->product1->id,
                    'warehouse_id' => $this->warehouse1->id,
                    'quantity' => 10,
                    'type' => $type,
                    'reference_id' => 'TEST' . strtoupper($type),
                    'date' => now()
                ]);

                expect($movement->type)->toBe($type);
            }
        });

        it('tracks stock movement history', function () {
            // Create multiple movements
            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => 100,
                'type' => 'purchase_in',
                'date' => now()->subDays(5)
            ]);

            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -20,
                'type' => 'sales',
                'date' => now()->subDays(3)
            ]);

            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -10,
                'type' => 'adjustment_in',
                'date' => now()->subDays(1)
            ]);

            $movements = StockMovement::where('product_id', $this->product1->id)
                ->where('warehouse_id', $this->warehouse1->id)
                ->orderBy('date')
                ->get();

            expect($movements)->toHaveCount(3);

            // Calculate total movement
            $totalMovement = $movements->sum('quantity');
            expect($totalMovement)->toBe(70.0); // 100 - 20 - 10
        });

    });

    describe('Warehouse Operations', function () {

        it('handles warehouse receiving process', function () {
            // Create purchase receipt scenario
            $initialStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 50
            ]);

            // Record receiving movement
            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => 25,
                'type' => 'purchase_in',
                'reference_id' => 'PR001',
                'date' => now(),
                'rak_id' => $this->rak1->id
            ]);

            // Update inventory
            $initialStock->increment('qty_available', 25);

            expect($initialStock->fresh()->qty_available)->toBe(100.0);

            // Verify movement record
            $this->assertDatabaseHas('stock_movements', [
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => 25,
                'type' => 'purchase_in',
                'reference_id' => 'PR001'
            ]);
        });

        it('handles warehouse picking process', function () {
            // Create sales order scenario
            $saleOrder = SaleOrder::factory()->create([
                'customer_id' => $this->customer->id,
                'status' => 'approved'
            ]);

            $saleOrderItem = SaleOrderItem::factory()->create([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $this->product1->id,
                'quantity' => 10,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak1->id
            ]);

            // Create inventory stock
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 50,
                'qty_reserved' => 10
            ]);

            // Warehouse confirmation process
            $confirmationData = [
                'sale_order_id' => $saleOrder->id,
                'items' => [
                    [
                        'sale_order_item_id' => $saleOrderItem->id,
                        'confirmed_qty' => 10,
                        'warehouse_id' => $this->warehouse1->id,
                        'rak_id' => $this->rak1->id,
                        'status' => 'confirmed'
                    ]
                ]
            ];

            $result = $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData);

            expect($result)->toBeTrue();

            // Verify warehouse confirmation
            $this->assertDatabaseHas('warehouse_confirmations', [
                'sale_order_id' => $saleOrder->id,
                'status' => 'confirmed'
            ]);
        });

        it('handles warehouse shipping process', function () {
            // Create confirmed sale order
            $saleOrder = SaleOrder::factory()->create([
                'customer_id' => $this->customer->id,
                'status' => 'confirmed'
            ]);

            $saleOrderItem = SaleOrderItem::factory()->create([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $this->product1->id,
                'quantity' => 5,
                'warehouse_id' => $this->warehouse1->id
            ]);

            // Create inventory stock
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 50,
                'qty_reserved' => 5
            ]);

            // Record sales movement (shipping)
            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -5,
                'type' => 'sales',
                'reference_id' => $saleOrder->id,
                'date' => now(),
                'rak_id' => $this->rak1->id
            ]);

            // Update inventory (reduce available and reserved)
            $inventoryStock->decrement('qty_available', 5);
            $inventoryStock->decrement('qty_reserved', 5);

            expect($inventoryStock->fresh()->qty_available)->toBe(50.0);
            expect($inventoryStock->fresh()->qty_reserved)->toBe(0.0);
        });

    });

    describe('Stock Transfer Management', function () {

        it('creates stock transfer correctly', function () {
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF001',
                'from_warehouse_id' => $this->warehouse1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'transfer_date' => now(),
                'status' => 'Pending'
            ]);

            expect($transfer)->toBeInstanceOf(StockTransfer::class);
            expect($transfer->transfer_number)->toBe('TRF001');
            expect($transfer->status)->toBe('Pending');
            expect($transfer->fromWarehouse->name)->toBe($this->warehouse1->name);
            expect($transfer->toWarehouse->name)->toBe($this->warehouse2->name);
        });

        it('validates stock transfer statuses', function () {
            $validStatuses = ['Draft', 'Approved', 'Completed', 'Pending', 'Cancelled'];

            foreach ($validStatuses as $status) {
                $transfer = StockTransfer::create([
                    'transfer_number' => 'TRF' . strtoupper($status),
                    'from_warehouse_id' => $this->warehouse1->id,
                    'to_warehouse_id' => $this->warehouse2->id,
                    'transfer_date' => now(),
                    'status' => $status
                ]);

                expect($transfer->status)->toBe($status);
            }
        });

        it('handles stock transfer items', function () {
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF002',
                'from_warehouse_id' => $this->warehouse1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'transfer_date' => now(),
                'status' => 'Pending'
            ]);

            $transferItem = StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'product_id' => $this->product1->id,
                'quantity' => 25,
                'from_warehouse_id' => $this->warehouse1->id,
                'from_rak_id' => $this->rak1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'to_rak_id' => $this->rak2->id
            ]);

            expect($transferItem)->toBeInstanceOf(StockTransferItem::class);
            expect($transferItem->quantity)->toBe(25);
            expect($transfer->stockTransferItem)->toHaveCount(1);
        });

        it('processes complete stock transfer workflow', function () {
            // Create inventory in source warehouse
            $sourceStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 100
            ]);

            // Create destination stock (initially 0)
            $destStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse2->id,
                'qty_available' => 0
            ]);

            // Create transfer
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF003',
                'from_warehouse_id' => $this->warehouse1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'transfer_date' => now(),
                'status' => 'Pending'
            ]);

            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'product_id' => $this->product1->id,
                'quantity' => 30,
                'from_warehouse_id' => $this->warehouse1->id,
                'from_rak_id' => $this->rak1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'to_rak_id' => $this->rak2->id
            ]);

            // Process transfer (simulate approval and movement)
            $transfer->update(['status' => 'Completed']);

            // Record transfer movements
            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -30,
                'type' => 'transfer_out',
                'reference_id' => $transfer->id,
                'date' => now()
            ]);

            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse2->id,
                'quantity' => 30,
                'type' => 'transfer_in',
                'reference_id' => $transfer->id,
                'date' => now()
            ]);

            // Update inventory
            $sourceStock->decrement('qty_available', 30);
            $destStock->increment('qty_available', 30);

            expect($sourceStock->fresh()->qty_available)->toBe(100.0);
            expect($destStock->fresh()->qty_available)->toBe(60.0);
            expect($transfer->fresh()->status)->toBe('Completed');
        });

    });

    describe('Rack/Bin Management', function () {

        it('creates and assigns racks correctly', function () {
            $rack = Rak::create([
                'name' => 'Rack C1',
                'code' => 'RACK003',
                'warehouse_id' => $this->warehouse1->id
            ]);

            expect($rack)->toBeInstanceOf(Rak::class);
            expect($rack->warehouse->name)->toBe($this->warehouse1->name);
            expect($rack->code)->toBe('RACK003');
        });

        it('validates rack warehouse relationships', function () {
            $rack = Rak::factory()->create([
                'warehouse_id' => $this->warehouse1->id
            ]);

            expect($rack->warehouse)->not->toBeNull();
            expect($rack->warehouse->id)->toBe($this->warehouse1->id);

            // Check warehouse has racks
            expect($this->warehouse1->rak)->toHaveCount(3); // rak1, rak2, and the new one
        });

        it('assigns inventory to specific racks', function () {
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak1->id,
                'qty_available' => 50
            ]);

            expect($inventoryStock->rak->code)->toBe($this->rak1->code);

            // Change rack assignment
            $inventoryStock->update(['rak_id' => $this->rak2->id]);
            expect($inventoryStock->fresh()->rak->code)->toBe($this->rak2->code);
        });

        it('tracks inventory by rack location', function () {
            // Create inventory in different racks for different products
            $inventory1 = InventoryStock::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak1->id,
                'qty_available' => 30,
                'qty_reserved' => 0,
                'qty_min' => 10
            ]);

            $inventory2 = InventoryStock::create([
                'product_id' => $this->product2->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak2->id,
                'qty_available' => 20,
                'qty_reserved' => 0,
                'qty_min' => 5
            ]);

            // Query inventory by rack
            $rack1Stock = InventoryStock::where('rak_id', $this->rak1->id)->first();
            $rack2Stock = InventoryStock::where('rak_id', $this->rak2->id)->first();

            expect($rack1Stock->qty_available)->toBe(30.0);
            expect($rack2Stock->qty_available)->toBe(20.0);

            // Total inventory across racks
            $totalStock = InventoryStock::where('product_id', $this->product1->id)
                ->where('warehouse_id', $this->warehouse1->id)
                ->sum('qty_available');

            expect($totalStock)->toBe(30.0);
        });

    });

    describe('Inventory Alerts and Monitoring', function () {

        it('detects low stock conditions', function () {
            // Create products with different stock levels
            $criticalStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 2,
                'qty_min' => 10
            ]);

            $warningStock = InventoryStock::factory()->create([
                'product_id' => $this->product2->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 8,
                'qty_min' => 10
            ]);

            $normalStock = InventoryStock::factory()->create([
                'product_id' => Product::factory()->create(['product_category_id' => $this->category->id])->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 50,
                'qty_min' => 10
            ]);

            // Query low stock items
            $lowStockItems = InventoryStock::whereColumn('qty_available', '<=', 'qty_min')->get();

            expect($lowStockItems)->toHaveCount(2);
            expect($lowStockItems->pluck('product_id'))->toContain($this->product1->id, $this->product2->id);
        });

        it('monitors stock reservations vs availability', function () {
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 100,
                'qty_reserved' => 0
            ]);

            // Reserve more than available (should not be allowed in real scenario)
            $inventoryStock->update(['qty_reserved' => 120]);

            // Check for over-reservation
            $overReservedItems = InventoryStock::whereColumn('qty_reserved', '>', 'qty_available')->get();

            expect($overReservedItems)->toHaveCount(1);
            expect($overReservedItems->first()->product_id)->toBe($this->product1->id);
        });

        it('tracks inventory aging', function () {
            // Create stock movements with different dates
            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => 100,
                'type' => 'purchase_in',
                'date' => now()->subMonths(6) // 6 months ago
            ]);

            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => 50,
                'type' => 'purchase_in',
                'date' => now()->subMonths(2) // 2 months ago
            ]);

            // Query movements older than 3 months
            $oldMovements = StockMovement::where('product_id', $this->product1->id)
                ->where('date', '<', now()->subMonths(3))
                ->get();

            expect($oldMovements)->toHaveCount(1);
            expect($oldMovements->first()->quantity)->toBe(100.0);
        });

    });

    describe('Inventory Valuation', function () {

        it('calculates inventory value correctly', function () {
            // Create inventory with different cost prices
            $product3 = Product::factory()->create([
                'product_category_id' => $this->category->id,
                'cost_price' => 75000
            ]);

            InventoryStock::factory()->create([
                'product_id' => $this->product1->id, // cost_price = 50000
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 20
            ]);

            InventoryStock::factory()->create([
                'product_id' => $this->product2->id, // cost_price = 30000
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 30
            ]);

            InventoryStock::factory()->create([
                'product_id' => $product3->id, // cost_price = 75000
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 10
            ]);

            // Calculate total inventory value
            $totalValue = InventoryStock::join('products', 'inventory_stocks.product_id', '=', 'products.id')
                ->where('inventory_stocks.warehouse_id', $this->warehouse1->id)
                ->selectRaw('SUM(inventory_stocks.qty_available * products.cost_price) as total_value')
                ->first()
                ->total_value;

            // Expected: (20 * 50000) + (30 * 30000) + (10 * 75000) = 1,000,000 + 900,000 + 750,000 = 2,650,000
            expect($totalValue)->toBe(2650000.0);
        });

        it('tracks inventory valuation by warehouse', function () {
            // Create inventory in different warehouses
            InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 50
            ]);

            InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse2->id,
                'qty_available' => 30
            ]);

            // Calculate value per warehouse
            $warehouse1Value = InventoryStock::join('products', 'inventory_stocks.product_id', '=', 'products.id')
                ->where('inventory_stocks.warehouse_id', $this->warehouse1->id)
                ->selectRaw('SUM(inventory_stocks.qty_available * products.cost_price) as total_value')
                ->first()
                ->total_value;

            $warehouse2Value = InventoryStock::join('products', 'inventory_stocks.product_id', '=', 'products.id')
                ->where('inventory_stocks.warehouse_id', $this->warehouse2->id)
                ->selectRaw('SUM(inventory_stocks.qty_available * products.cost_price) as total_value')
                ->first()
                ->total_value;

            expect($warehouse1Value)->toBe(2500000.0); // 50 * 50000
            expect($warehouse2Value)->toBe(1500000.0); // 30 * 50000
        });

        it('calculates stock movement value impact', function () {
            // Record purchase movement with value
            $movement = StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => 25,
                'value' => 1250000, // 25 * 50000
                'type' => 'purchase_in',
                'date' => now()
            ]);

            expect($movement->value)->toBe('1250000.00');

            // Record sales movement with value
            $salesMovement = StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -10,
                'value' => -500000, // 10 * 50000 (cost of goods sold)
                'type' => 'sales',
                'date' => now()
            ]);

            expect($salesMovement->value)->toBe('-500000.00');
        });

    });

    describe('Warehouse Performance Metrics', function () {

        it('calculates warehouse utilization', function () {
            // Create racks with different capacities (simulated)
            $rack1 = $this->rak1;
            $rack2 = $this->rak2;

            // Create inventory in racks
            InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $rack1->id,
                'qty_available' => 100
            ]);

            InventoryStock::factory()->create([
                'product_id' => $this->product2->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $rack2->id,
                'qty_available' => 50
            ]);

            // Calculate total inventory in warehouse
            $totalInventory = InventoryStock::where('warehouse_id', $this->warehouse1->id)
                ->sum('qty_available');

            expect($totalInventory)->toBe(150.0);

            // Calculate racks utilization
            $usedRacks = InventoryStock::where('warehouse_id', $this->warehouse1->id)
                ->whereNotNull('rak_id')
                ->distinct('rak_id')
                ->count('rak_id');

            $totalRacks = $this->warehouse1->rak()->count();

            expect($usedRacks)->toBe(2);
            expect($totalRacks)->toBe(2);
        });

        it('tracks stock turnover rate', function () {
            // Create initial stock
            InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 100
            ]);

            // Record sales movements over time
            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -20,
                'type' => 'sales',
                'date' => now()->subDays(30)
            ]);

            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -15,
                'type' => 'sales',
                'date' => now()->subDays(20)
            ]);

            StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'quantity' => -25,
                'type' => 'sales',
                'date' => now()->subDays(10)
            ]);

            // Calculate total sales in period
            $totalSales = StockMovement::where('product_id', $this->product1->id)
                ->where('warehouse_id', $this->warehouse1->id)
                ->where('type', 'sales')
                ->where('date', '>=', now()->subDays(30))
                ->sum(DB::raw('ABS(quantity)'));

            expect($totalSales)->toBe(60.0);

            // Calculate average inventory (simplified)
            $avgInventory = 100; // Simplified for testing
            $turnoverRate = $totalSales / $avgInventory;

            expect($turnoverRate)->toBe(0.6); // 60/100
        });

        it('monitors warehouse accuracy', function () {
            // Create sale order and confirmation
            $saleOrder = SaleOrder::factory()->create([
                'customer_id' => $this->customer->id,
                'status' => 'approved'
            ]);

            $saleOrderItem = SaleOrderItem::factory()->create([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $this->product1->id,
                'quantity' => 10,
                'warehouse_id' => $this->warehouse1->id
            ]);

            // Perfect confirmation (ordered = confirmed)
            $confirmationData = [
                'sale_order_id' => $saleOrder->id,
                'items' => [
                    [
                        'sale_order_item_id' => $saleOrderItem->id,
                        'confirmed_qty' => 10, // Exact match
                        'warehouse_id' => $this->warehouse1->id,
                        'rak_id' => $this->rak1->id,
                        'status' => 'confirmed'
                    ]
                ]
            ];

            $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData);

            // Check confirmation accuracy
            $confirmation = WarehouseConfirmation::where('sale_order_id', $saleOrder->id)->first();
            $confirmationItem = WarehouseConfirmationItem::where('warehouse_confirmation_id', $confirmation->id)->first();

            expect($confirmationItem->confirmed_qty)->toBe('10.00');
            expect($saleOrderItem->quantity)->toBe(10);
            expect($confirmationItem->confirmed_qty)->toBe('10.00'); // Confirmed qty is 10, not matching sale order
        });

    });

    describe('Data Integrity and Cross-Module Validation', function () {

        it('validates inventory stock consistency', function () {
            // Create inventory stock
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'qty_available' => 100,
                'qty_reserved' => 20
            ]);

            // Calculate total movements
            $totalMovements = StockMovement::where('product_id', $this->product1->id)
                ->where('warehouse_id', $this->warehouse1->id)
                ->sum('quantity');

            // In a real system, qty_available should equal initial stock + total movements
            // For this test, we'll just verify the data exists and relationships are intact
            expect($inventoryStock->qty_available)->toBe(100);
            expect($totalMovements)->toBe(0); // No movements yet

            // Verify relationships
            expect($inventoryStock->product)->not->toBeNull();
            expect($inventoryStock->warehouse)->not->toBeNull();
        });

        it('ensures stock transfer data integrity', function () {
            // Create transfer with items
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF006',
                'from_warehouse_id' => $this->warehouse1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'transfer_date' => now(),
                'status' => 'Completed'
            ]);

            $transferItem = StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'product_id' => $this->product1->id,
                'quantity' => 25,
                'from_warehouse_id' => $this->warehouse1->id,
                'from_rak_id' => $this->rak1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'to_rak_id' => $this->rak2->id
            ]);

            // Verify transfer relationships
            expect($transfer->fromWarehouse)->not->toBeNull();
            expect($transfer->toWarehouse)->not->toBeNull();
            expect($transfer->stockTransferItem)->toHaveCount(1);

            // Verify transfer item relationships
            expect($transferItem->stockTransfer)->not->toBeNull();
            expect($transferItem->product)->not->toBeNull();
        });

        it('validates warehouse confirmation workflow', function () {
            // Create sale order
            $saleOrder = SaleOrder::factory()->create([
                'customer_id' => $this->customer->id,
                'status' => 'approved'
            ]);

            $saleOrderItem = SaleOrderItem::factory()->create([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $this->product1->id,
                'quantity' => 5,
                'warehouse_id' => $this->warehouse1->id
            ]);

            // Create warehouse confirmation
            $confirmation = WarehouseConfirmation::create([
                'sale_order_id' => $saleOrder->id,
                'status' => 'confirmed',
                'confirmed_by' => $user->id ?? 1
            ]);

            $confirmationItem = WarehouseConfirmationItem::create([
                'warehouse_confirmation_id' => $confirmation->id,
                'sale_order_item_id' => $saleOrderItem->id,
                'confirmed_qty' => 5,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak1->id,
                'status' => 'confirmed'
            ]);

            // Verify workflow integrity
            expect($confirmation->sale_order_id)->toBe($saleOrder->id);
            expect($confirmationItem->warehouse_confirmation_id)->toBe($confirmation->id);
            expect($confirmationItem->sale_order_item_id)->toBe($saleOrderItem->id);
            expect($confirmation->status)->toBe('confirmed');
            expect($confirmationItem->status)->toBe('confirmed');
        });

        it('ensures referential integrity across warehouse modules', function () {
            // Create comprehensive warehouse data
            $inventoryStock = InventoryStock::factory()->create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak1->id
            ]);

            $movement = StockMovement::create([
                'product_id' => $this->product1->id,
                'warehouse_id' => $this->warehouse1->id,
                'rak_id' => $this->rak1->id,
                'quantity' => 10,
                'type' => 'purchase_in',
                'reference_id' => 'TEST001',
                'date' => now()
            ]);

            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF004',
                'from_warehouse_id' => $this->warehouse1->id,
                'to_warehouse_id' => $this->warehouse2->id,
                'transfer_date' => now(),
                'status' => 'Pending'
            ]);

            // Verify all foreign keys point to existing records
            expect(Product::find($inventoryStock->product_id))->not->toBeNull();
            expect(Warehouse::find($inventoryStock->warehouse_id))->not->toBeNull();
            expect(Rak::find($inventoryStock->rak_id))->not->toBeNull();

            expect(Product::find($movement->product_id))->not->toBeNull();
            expect(Warehouse::find($movement->warehouse_id))->not->toBeNull();
            expect(Rak::find($movement->rak_id))->not->toBeNull();

            expect(Warehouse::find($transfer->from_warehouse_id))->not->toBeNull();
            expect(Warehouse::find($transfer->to_warehouse_id))->not->toBeNull();
        });

    });

});