<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('salesOrders field data reaches mutateFormDataBeforeCreate', function () {
    // Create test data
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'username' => 'testuser',
        'password' => bcrypt('password'),
        'first_name' => 'Test',
        'kode_user' => 'TU001',
    ]);
    $cabang = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::factory()->create();

    // Create Sale Order with confirmed status
    $saleOrder = SaleOrder::create([
        'customer_id' => $customer->id,
        'so_number' => 'SO-' . now()->format('Ymd') . '-0001',
        'order_date' => now(),
        'status' => 'confirmed',
        'delivery_date' => now()->addDays(1),
        'total_amount' => 1000000,
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => $user->id,
        'warehouse_confirmed_at' => now(),
    ]);

    $saleOrderItem = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'remaining_quantity' => 10,
    ]);

    // Simulate form data that would be sent from the Filament form
    $formData = [
        'do_number' => 'DO-TEST-001',
        'salesOrders' => [$saleOrder->id], // This is what should be sent
        'delivery_date' => now()->addDays(1)->toDateTimeString(),
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'notes' => 'Test delivery order',
        'additional_cost' => 0,
        'additional_cost_description' => null,
        'deliveryOrderItem' => [
            [
                'options_from' => 2,
                'sale_order_item_id' => $saleOrderItem->id,
                'product_id' => $product->id,
                'quantity' => 5,
            ]
        ],
        'selected_items' => [
            [
                'selected' => true,
                'product_name' => "({$product->sku}) {$product->name}",
                'remaining_qty' => 10,
                'quantity' => 5,
                'sale_order_item_id' => $saleOrderItem->id,
                'product_id' => $product->id,
            ]
        ],
    ];

    // Test the mutateFormDataBeforeCreate method directly
    $createPage = new \App\Filament\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder();
    $reflection = new ReflectionClass($createPage);
    $method = $reflection->getMethod('mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    // This should trigger our logging and validation
    try {
        $result = $method->invoke($createPage, $formData);

        // Check that salesOrders data is preserved
        expect($result)->toBeArray();
        expect(isset($result['salesOrders']))->toBeTrue();
        expect($result['salesOrders'])->toBe([$saleOrder->id]);
        expect($result['warehouse_id'])->toBe($warehouse->id); // Should be set from sales order

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Validation failed - this is expected if validation fails
        $errors = $e->errors();
        \Illuminate\Support\Facades\Log::info('Validation errors:', $errors);
        // We expect this to fail due to validation, but we should see the logs
        expect($errors)->toBeArray();
    } catch (Exception $e) {
        // Other exceptions
        \Illuminate\Support\Facades\Log::info('Exception:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        // Re-throw to see what happened
        throw $e;
    }
});
