<?php

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use App\Models\User;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

describe('Warehouse Confirmation Feature', function () {

    beforeEach(function () {
        // Create authenticated user
        $user = User::factory()->create();
        Auth::login($user);

        // Create required master data
        $this->category = ProductCategory::factory()->create([
            'kode' => 'CAT001'
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'kode' => 'WH001',
            'location' => 'Main Location'
        ]);

        $this->rak = Rak::factory()->create([
            'code' => 'RACK001',
            'warehouse_id' => $this->warehouse->id
        ]);

        $this->customer = Customer::factory()->create();

        $this->product = Product::factory()->create([
            'product_category_id' => $this->category->id
        ]);

        $this->salesOrderService = app(SalesOrderService::class);
    });

    it('can fully confirm warehouse for approved SO', function () {
        // Create approved SO
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => null
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id
        ]);

        // Warehouse confirmation process
        $confirmationData = [
            'sale_order_id' => $saleOrder->id,
            'items' => [
                [
                    'sale_order_item_id' => $saleOrderItem->id,
                    'confirmed_qty' => 10,
                    'warehouse_id' => $this->warehouse->id,
                    'rak_id' => $this->rak->id,
                    'status' => 'confirmed'
                ]
            ],
            'notes' => 'All items picked and packed successfully'
        ];

        // Simulate warehouse confirmation
        $result = $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData);

        expect($result)->toBeTrue();
        expect($saleOrder->fresh()->status)->toBe('confirmed');

        // Check if warehouse confirmation record exists
        $this->assertDatabaseHas('warehouse_confirmations', [
            'sale_order_id' => $saleOrder->id,
            'status' => 'confirmed'
        ]);
    });

    it('can partially confirm warehouse for approved SO', function () {
        // Create approved SO
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => null
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id
        ]);

        // Partial confirmation (only 7 out of 10 items available)
        $confirmationData = [
            'sale_order_id' => $saleOrder->id,
            'items' => [
                [
                    'sale_order_item_id' => $saleOrderItem->id,
                    'confirmed_qty' => 7,
                    'warehouse_id' => $this->warehouse->id,
                    'rak_id' => $this->rak->id,
                    'status' => 'confirmed'
                ]
            ],
            'notes' => 'Only 7 items available, 3 items missing'
        ];

        $result = $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData);

        expect($result)->toBeTrue();
        expect($saleOrder->fresh()->status)->toBe('confirmed');

        $this->assertDatabaseHas('warehouse_confirmations', [
            'sale_order_id' => $saleOrder->id,
            'status' => 'confirmed'
        ]);
    });

    it('can reject warehouse confirmation when items unavailable', function () {
        // Create approved SO
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => null
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id
        ]);

        // Reject confirmation (no items available)
        $confirmationData = [
            'sale_order_id' => $saleOrder->id,
            'items' => [
                [
                    'sale_order_item_id' => $saleOrderItem->id,
                    'confirmed_qty' => 0,
                    'warehouse_id' => $this->warehouse->id,
                    'rak_id' => $this->rak->id,
                    'status' => 'rejected'
                ]
            ],
            'notes' => 'Items not available in warehouse'
        ];

        $result = $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData);

        expect($result)->toBeTrue();
        expect($saleOrder->fresh()->status)->toBe('reject');

        $this->assertDatabaseHas('warehouse_confirmations', [
            'sale_order_id' => $saleOrder->id,
            'status' => 'rejected'
        ]);
    });

    it('prevents warehouse confirmation for non-approved SO', function () {
        // Create draft SO (not approved)
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => null
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id
        ]);

        $confirmationData = [
            'sale_order_id' => $saleOrder->id,
            'items' => [
                [
                    'sale_order_item_id' => $saleOrderItem->id,
                    'confirmed_qty' => 10,
                    'warehouse_id' => $this->warehouse->id,
                    'rak_id' => $this->rak->id,
                    'status' => 'confirmed'
                ]
            ],
            'notes' => 'Attempting to confirm draft SO'
        ];

        // Should throw exception or return false
        expect(fn() => $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData))
            ->toThrow(Exception::class, 'Sales Order must be approved before warehouse confirmation');
    });

    it('updates SO status after warehouse confirmation', function () {
        // Create approved SO
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => null
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id
        ]);

        // Full confirmation
        $confirmationData = [
            'sale_order_id' => $saleOrder->id,
            'items' => [
                [
                    'sale_order_item_id' => $saleOrderItem->id,
                    'confirmed_qty' => 10,
                    'warehouse_id' => $this->warehouse->id,
                    'rak_id' => $this->rak->id,
                    'status' => 'confirmed'
                ]
            ],
            'notes' => 'Ready for delivery'
        ];

        $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData);

        $updatedSO = $saleOrder->fresh();
        expect($updatedSO->status)->toBe('confirmed');
        expect($updatedSO->warehouse_confirmed_at)->not->toBeNull();
    });

    it('allows proceeding to delivery after full confirmation', function () {
        // Create confirmed SO
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => null,
            'warehouse_confirmed_at' => now()
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id
        ]);

        // Create warehouse confirmation for the SO
        $warehouseConfirmation = WarehouseConfirmation::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'status' => 'confirmed',
            'confirmed_by' => 1,
            'confirmed_at' => now()
        ]);

        WarehouseConfirmationItem::factory()->create([
            'warehouse_confirmation_id' => $warehouseConfirmation->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'confirmed_qty' => 10,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'status' => 'confirmed'
        ]);

        // Test that delivery order can be created
        $deliveryOrderData = [
            'sale_order_id' => $saleOrder->id,
            'delivery_date' => now()->addDays(1),
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Proceeding to delivery after warehouse confirmation'
        ];

        // Assuming there's a method to create delivery order
        $result = $this->salesOrderService->createDeliveryOrder($saleOrder, $deliveryOrderData);

        expect($result)->toBeTrue();

        $this->assertDatabaseHas('delivery_orders', [
            'do_number' => 'DO-' . now()->format('Ymd') . '-0001',
            'status' => 'draft'
        ]);
    });

    it('tracks warehouse confirmation details', function () {
        // Create approved SO
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => null
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id
        ]);

        $confirmationData = [
            'sale_order_id' => $saleOrder->id,
            'items' => [
                [
                    'sale_order_item_id' => $saleOrderItem->id,
                    'confirmed_qty' => 10,
                    'warehouse_id' => $this->warehouse->id,
                    'rak_id' => $this->rak->id,
                    'status' => 'confirmed'
                ]
            ],
            'notes' => 'Items picked from rack A1, packed securely'
        ];

        $this->salesOrderService->confirmWarehouse($saleOrder, $confirmationData);

        // Check warehouse confirmation details
        $this->assertDatabaseHas('warehouse_confirmation_items', [
            'sale_order_item_id' => $saleOrderItem->id,
            'confirmed_qty' => 10,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'status' => 'confirmed'
        ]);
    });
});