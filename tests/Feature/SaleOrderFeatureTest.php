<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Services\SalesOrderService;
use App\Services\QuotationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->salesOrderService = app(SalesOrderService::class);
    $this->quotationService = app(QuotationService::class);

    // Create test data
    $this->productCategory = ProductCategory::create([
        'name' => 'Test Category',
        'code' => 'TC001',
        'kode' => 'TC001',
        'cabang_id' => 1,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Main Warehouse',
        'code' => 'WH001',
        'kode' => 'WH001',
        'cabang_id' => 1,
        'branch_id' => 1,
        'address' => 'Jl. Test No. 123',
        'location' => 'Jakarta',
    ]);

    $this->rak = Rak::create([
        'name' => 'Rack A1',
        'code' => 'A1',
        'warehouse_id' => $this->warehouse->id,
        'type' => 'shelf',
    ]);
});

test('can create sales order from quotation', function () {
    // Create customer
    $customer = Customer::create([
        'name' => 'PT Test Customer',
        'code' => 'CUST001',
        'address' => 'Jl. Test No. 123',
        'telephone' => '021-1234567',
        'phone' => '081234567890',
        'email' => 'test@customer.com',
        'perusahaan' => 'PT Test Customer',
        'tipe' => 'PKP',
        'fax' => '021-1234568',
        'nik_npwp' => '1234567890123456',
        'tempo_kredit' => 30,
        'kredit_limit' => 10000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Test customer',
    ]);

    // Create product
    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'PROD001',
        'cabang_id' => 1,
        'product_category_id' => $this->productCategory->id,
        'sell_price' => 100000,
        'cost_price' => 80000,
        'kode_merk' => 'TEST',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    // Create quotation
    $quotation = Quotation::create([
        'quotation_number' => 'QT-20251101-0001',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'status' => 'approve',
        'created_by' => 1,
    ]);

    // Add quotation item
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 10,
        'tax' => 11,
    ]);

    // Create sales order from quotation
    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0001',
        'quotation_id' => $quotation->id,
        'customer_id' => $quotation->customer_id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(7),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    // Add sales order item
    SaleOrderItem::create([
        'sale_order_id' => $salesOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 10,
        'tax' => 11,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => $this->rak->id,
    ]);

    expect($salesOrder)->toBeInstanceOf(SaleOrder::class)
        ->and($salesOrder->quotation_id)->toBe($quotation->id)
        ->and($salesOrder->customer_id)->toBe($quotation->customer_id)
        ->and($salesOrder->status)->toBe('draft')
        ->and($salesOrder->saleOrderItem)->toHaveCount(1);
});

test('can create sales order directly', function () {
    // Create customer
    $customer = Customer::create([
        'name' => 'PT Direct Customer',
        'code' => 'CUST002',
        'address' => 'Jl. Direct No. 456',
        'telephone' => '021-7654321',
        'phone' => '081987654321',
        'email' => 'direct@customer.com',
        'perusahaan' => 'PT Direct Customer',
        'tipe' => 'PKP',
        'fax' => '021-7654322',
        'nik_npwp' => '6543210987654321',
        'tempo_kredit' => 30,
        'kredit_limit' => 5000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Direct customer',
    ]);

    // Create product
    $product = Product::create([
        'name' => 'Direct Product',
        'sku' => 'PROD002',
        'cabang_id' => 1,
        'product_category_id' => $this->productCategory->id,
        'sell_price' => 200000,
        'cost_price' => 160000,
        'kode_merk' => 'DIRECT',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    // Create sales order directly
    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0002',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(5),
        'status' => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by' => 1,
    ]);

    // Add sales order item
    SaleOrderItem::create([
        'sale_order_id' => $salesOrder->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 200000,
        'discount' => 5,
        'tax' => 11,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => $this->rak->id,
    ]);

    expect($salesOrder)->toBeInstanceOf(SaleOrder::class)
        ->and($salesOrder->quotation_id)->toBeNull()
        ->and($salesOrder->customer_id)->toBe($customer->id)
        ->and($salesOrder->status)->toBe('draft')
        ->and($salesOrder->tipe_pengiriman)->toBe('Ambil Sendiri')
        ->and($salesOrder->saleOrderItem)->toHaveCount(1);
});

test('sales order approval workflow works correctly', function () {
    // Mock authenticated user
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
    Auth::shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->andReturnSelf();

    $customer = Customer::create([
        'name' => 'PT Workflow Customer',
        'code' => 'CUST003',
        'address' => 'Jl. Workflow No. 789',
        'telephone' => '021-1111111',
        'phone' => '081111111111',
        'email' => 'workflow@customer.com',
        'perusahaan' => 'PT Workflow Customer',
        'tipe' => 'PKP',
        'fax' => '021-1111112',
        'nik_npwp' => '1111111111111111',
        'tempo_kredit' => 30,
        'kredit_limit' => 20000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Workflow test customer',
    ]);

    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0003',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(10),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    // Test request approve
    $result = $this->salesOrderService->requestApprove($salesOrder);
    expect($result)->toBeTrue();

    $salesOrder->refresh();
    expect($salesOrder->status)->toBe('request_approve')
        ->and($salesOrder->request_approve_by)->toBe($user->id)
        ->and($salesOrder->request_approve_at)->toBeInstanceOf(Carbon::class);

    // Test approve
    $result = $this->salesOrderService->approve($salesOrder);
    expect($result)->toBeTrue();

    $salesOrder->refresh();
    expect($salesOrder->status)->toBe('confirmed')
        ->and($salesOrder->approve_by)->toBe($user->id)
        ->and($salesOrder->approve_at)->toBeInstanceOf(Carbon::class);

    // Test reject on a new sales order
    $salesOrder2 = SaleOrder::create([
        'so_number' => 'SO-20251101-0004',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(10),
        'status' => 'request_approve',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    $result = $this->salesOrderService->reject($salesOrder2);
    expect($result)->toBeTrue();

    $salesOrder2->refresh();
    expect($salesOrder2->status)->toBe('reject')
        ->and($salesOrder2->reject_by)->toBe($user->id)
        ->and($salesOrder2->reject_at)->toBeInstanceOf(Carbon::class);
});

test('sales order can calculate total amount correctly', function () {
    $customer = Customer::create([
        'name' => 'PT Total Customer',
        'code' => 'CUST004',
        'address' => 'Jl. Total No. 999',
        'telephone' => '021-9999999',
        'phone' => '081999999999',
        'email' => 'total@customer.com',
        'perusahaan' => 'PT Total Customer',
        'tipe' => 'PKP',
        'fax' => '021-9999998',
        'nik_npwp' => '9999999999999999',
        'tempo_kredit' => 30,
        'kredit_limit' => 10000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Total calculation test',
    ]);

    $product1 = Product::create([
        'name' => 'Product A',
        'sku' => 'PRODA',
        'cabang_id' => 1,
        'product_category_id' => $this->productCategory->id,
        'sell_price' => 100000,
        'cost_price' => 80000,
        'kode_merk' => 'A',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    $product2 = Product::create([
        'name' => 'Product B',
        'sku' => 'PRODB',
        'cabang_id' => 1,
        'product_category_id' => $this->productCategory->id,
        'sell_price' => 200000,
        'cost_price' => 160000,
        'kode_merk' => 'B',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0005',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(7),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    // Item 1: 2 × 100000 = 200000, discount 10% = 180000, tax 11% (inklusif) = 180000
    $item1 = SaleOrderItem::create([
        'sale_order_id' => $salesOrder->id,
        'product_id' => $product1->id,
        'quantity' => 2,
        'unit_price' => 100000,
        'discount' => 10,
        'tax' => 11,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => $this->rak->id,
    ]);

    // Item 2: 1 × 200000 = 200000, discount 5% = 190000, tax 11% (inklusif) = 190000
    $item2 = SaleOrderItem::create([
        'sale_order_id' => $salesOrder->id,
        'product_id' => $product2->id,
        'quantity' => 1,
        'unit_price' => 200000,
        'discount' => 5,
        'tax' => 11,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => $this->rak->id,
    ]);

    // Calculate total: 180000 + 190000 = 370000 (inklusif tax)
    $this->salesOrderService->updateTotalAmount($salesOrder);

    $salesOrder->refresh();
    // DB stores totals as decimal strings; compare numerically to avoid formatting differences
    expect((float) $salesOrder->total_amount)->toBe(370000.0);
});

test('sales order can be confirmed by warehouse', function () {
    $customer = Customer::create([
        'name' => 'PT Confirm Customer',
        'code' => 'CUST005',
        'address' => 'Jl. Confirm No. 111',
        'telephone' => '021-1111111',
        'phone' => '081111111111',
        'email' => 'confirm@customer.com',
        'perusahaan' => 'PT Confirm Customer',
        'tipe' => 'PKP',
        'fax' => '021-1111112',
        'nik_npwp' => '1111111111111111',
        'tempo_kredit' => 30,
        'kredit_limit' => 15000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Warehouse confirmation test',
    ]);

    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0006',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(3),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    // Warehouse confirms the order
    $result = $this->salesOrderService->confirm($salesOrder);
    expect($result)->toBeTrue();

    $salesOrder->refresh();
    expect($salesOrder->status)->toBe('confirmed');
});

test('sales order can be completed', function () {
    $customer = Customer::create([
        'name' => 'PT Complete Customer',
        'code' => 'CUST006',
        'address' => 'Jl. Complete No. 222',
        'telephone' => '021-2222222',
        'phone' => '081222222222',
        'email' => 'complete@customer.com',
        'perusahaan' => 'PT Complete Customer',
        'tipe' => 'PKP',
        'fax' => '021-2222223',
        'nik_npwp' => '2222222222222222',
        'tempo_kredit' => 30,
        'kredit_limit' => 12000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Completion test',
    ]);

    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0007',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(5),
        'status' => 'confirmed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    // Complete the sales order
    $result = $this->salesOrderService->completed($salesOrder);
    expect($result)->toBeTrue();

    $salesOrder->refresh();
    expect($salesOrder->status)->toBe('completed')
        ->and($salesOrder->completed_at)->toBeInstanceOf(Carbon::class);
});

test('sales order can be closed', function () {
    // Mock authenticated user
    $user = User::factory()->create([
        'name' => 'Close User',
        'email' => 'close@example.com',
    ]);
    Auth::shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->andReturnSelf();

    $customer = Customer::create([
        'name' => 'PT Close Customer',
        'code' => 'CUST007',
        'address' => 'Jl. Close No. 333',
        'telephone' => '021-3333333',
        'phone' => '081333333333',
        'email' => 'close@customer.com',
        'perusahaan' => 'PT Close Customer',
        'tipe' => 'PKP',
        'fax' => '021-3333334',
        'nik_npwp' => '3333333333333333',
        'tempo_kredit' => 30,
        'kredit_limit' => 8000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Close test',
    ]);

    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0008',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(7),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    // Request to close
    $result = $this->salesOrderService->requestClose($salesOrder);
    expect($result)->toBeTrue();

    $salesOrder->refresh();
    expect($salesOrder->status)->toBe('request_close')
        ->and($salesOrder->request_close_by)->toBe($user->id)
        ->and($salesOrder->request_close_at)->toBeInstanceOf(Carbon::class);

    // Close the sales order
    $result = $this->salesOrderService->close($salesOrder);
    expect($result)->toBeTrue();

    $salesOrder->refresh();
    expect($salesOrder->status)->toBe('closed')
        ->and($salesOrder->close_by)->toBe($user->id)
        ->and($salesOrder->close_at)->toBeInstanceOf(Carbon::class);
});

test('sales order reserves stock', function () {
    $customer = Customer::create([
        'name' => 'PT Reserve Customer',
        'code' => 'CUST008',
        'address' => 'Jl. Reserve No. 444',
        'telephone' => '021-4444444',
        'phone' => '081444444444',
        'email' => 'reserve@customer.com',
        'perusahaan' => 'PT Reserve Customer',
        'tipe' => 'PKP',
        'fax' => '021-4444445',
        'nik_npwp' => '4444444444444444',
        'tempo_kredit' => 30,
        'kredit_limit' => 25000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Stock reservation test',
    ]);

    $product = Product::create([
        'name' => 'Reserve Product',
        'sku' => 'PRODRES',
        'cabang_id' => 1,
        'product_category_id' => $this->productCategory->id,
        'sell_price' => 150000,
        'cost_price' => 120000,
        'kode_merk' => 'RESERVE',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    // Create inventory stock for the product
    \App\Models\InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => $this->rak->id,
        'qty_available' => 10,
        'qty_reserved' => 0,
        'qty_on_hand' => 10,
        'last_stock_update' => now(),
    ]);

    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0009',
        'customer_id' => $customer->id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(2),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $salesOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 150000,
        'discount' => 0,
        'tax' => 11,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => $this->rak->id,
    ]);

    // Confirm the sales order to trigger stock reservation
    $result = $this->salesOrderService->confirm($salesOrder);
    expect($result)->toBeTrue();

    $salesOrder->refresh();
    expect($salesOrder->status)->toBe('confirmed');

    // Check stock reservation
    $reservation = StockReservation::where('sale_order_id', $salesOrder->id)->first();
    expect($reservation)->not->toBeNull()
        ->and((float) $reservation->quantity)->toBe(5.0)
        ->and($reservation->product_id)->toBe($product->id)
        ->and($reservation->warehouse_id)->toBe($this->warehouse->id)
        ->and($reservation->rak_id)->toBe($this->rak->id);
});