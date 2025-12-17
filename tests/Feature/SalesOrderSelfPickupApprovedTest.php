<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Services\SalesOrderService;

test('sales order self pickup approved does not create stock reservation', function () {
    // Create test data
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'username' => 'testuser',
        'password' => bcrypt('password'),
        'first_name' => 'Test',
        'kode_user' => 'TU001',
    ]);

    $cabang = Cabang::create([
        'nama' => 'Test Cabang',
        'kode' => 'TC001',
        'alamat' => 'Jl. Test No. 123',
        'telepon' => '021-1234567',
    ]);

    // Create essential COAs for invoice creation
    ChartOfAccount::create([
        'code' => '1120',
        'name' => 'Accounts Receivable',
        'type' => 'asset',
        'level' => 3,
        'cabang_id' => $cabang->id,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'code' => '4000',
        'name' => 'Revenue/Sales',
        'type' => 'revenue',
        'level' => 2,
        'cabang_id' => $cabang->id,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'code' => '2120.06',
        'name' => 'PPn Keluaran',
        'type' => 'liability',
        'level' => 4,
        'cabang_id' => $cabang->id,
        'is_active' => true,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Test Warehouse',
        'code' => 'WH001',
        'kode' => 'WH001',
        'cabang_id' => $cabang->id,
        'branch_id' => $cabang->id,
        'address' => 'Jl. Test Warehouse',
        'location' => 'Jakarta',
    ]);

    $rak = Rak::create([
        'name' => 'Test Rak',
        'code' => 'R001',
        'warehouse_id' => $warehouse->id,
        'type' => 'shelf',
    ]);

    $productCategory = ProductCategory::create([
        'name' => 'Test Category',
        'code' => 'PC001',
        'kode' => 'PC001',
        'cabang_id' => $cabang->id,
    ]);

    $customer = Customer::create([
        'name' => 'Test Customer',
        'code' => 'CUST001',
        'address' => 'Jl. Test Customer',
        'telephone' => '021-7654321',
        'phone' => '081234567890',
        'email' => 'test@customer.com',
        'perusahaan' => 'PT Test Customer',
        'tipe' => 'PKP',
        'fax' => '021-7654322',
        'nik_npwp' => '1234567890123456',
        'tempo_kredit' => 30,
        'kredit_limit' => 10000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan' => 'Test customer for self pickup',
        'cabang_id' => $cabang->id,
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'PROD001',
        'cabang_id' => $cabang->id,
        'product_category_id' => $productCategory->id,
        'sell_price' => 100000,
        'cost_price' => 80000,
        'kode_merk' => 'TEST',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    // Create inventory stock
    InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 50,
        'qty_reserved' => 0,
    ]);

    $salesOrderService = app(SalesOrderService::class);

    // Authenticate user
    $this->actingAs($user);

    // Create sales order with self pickup
    $saleOrder = SaleOrder::create([
        'customer_id' => $customer->id,
        'so_number' => 'SO-' . now()->format('Ymd') . '-0001',
        'order_date' => now(),
        'status' => 'draft',
        'delivery_date' => now()->addDays(1),
        'total_amount' => 500000, // 5 units * 100000
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by' => $user->id,
    ]);

    $saleOrderItem = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    // Approve sales order
    $salesOrderService->requestApprove($saleOrder);
    $salesOrderService->approve($saleOrder);

    $saleOrder->refresh();
    expect($saleOrder->status)->toBe('approved');

    // Check that no stock reservation is created for self pickup
    $stockReservations = StockReservation::where('sale_order_id', $saleOrder->id)->get();
    expect($stockReservations)->toHaveCount(0);

    // Check inventory remains unchanged
    $inventoryStock = InventoryStock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($inventoryStock->qty_available)->toEqual(50);
    expect($inventoryStock->qty_reserved)->toEqual(0);
    expect($inventoryStock->qty_on_hand)->toEqual(50);

    // Complete the sales order
    $salesOrderService->completed($saleOrder);

    $saleOrder->refresh();
    expect($saleOrder->status)->toBe('completed');

    // Check inventory after completion (stock reduced via stock movement)
    $inventoryStock->refresh();
    expect($inventoryStock->qty_available)->toEqual(45);
    expect($inventoryStock->qty_reserved)->toEqual(0);
    expect($inventoryStock->qty_on_hand)->toEqual(45);
});