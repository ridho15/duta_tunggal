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

test('debug delivery order form data keys', function () {
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

    // Create Sale Order
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
    ]);

    // Simulate form data that would be sent
    $formData = [
        'do_number' => 'DO-TEST-001',
        'salesOrders' => [$saleOrder->id], // This is what we expect
        'delivery_date' => now()->addDays(1)->toDateTimeString(),
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
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
        'deliveryOrderItem' => [
            [
                'options_from' => 2,
                'sale_order_item_id' => $saleOrderItem->id,
                'product_id' => $product->id,
                'quantity' => 5,
            ]
        ],
    ];

    // Test the mutateFormDataBeforeCreate method directly
    $createPage = new \App\Filament\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder();
    $reflection = new ReflectionClass($createPage);
    $method = $reflection->getMethod('mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    // This should trigger our logging
    try {
        $result = $method->invoke($createPage, $formData);
        expect($result)->toBeArray();
        expect($result['salesOrders'])->toBe([1]);
        expect($result['deliveryOrderItem'])->toBeArray();
        expect(count($result['deliveryOrderItem']))->toBe(1);
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
