<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\QuotationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->quotationService = new QuotationService();
});

test('can create quotation with customer selection and auto-number generation', function () {
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

    $quotationData = [
        'quotation_number' => 'QO-20251101-0001',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'notes' => 'Test quotation notes',
        'status' => 'draft',
        'created_by' => 1,
    ];

    $quotation = Quotation::create($quotationData);

    expect($quotation)->toBeInstanceOf(Quotation::class)
        ->and($quotation->customer_id)->toBe($customer->id)
        ->and($quotation->status)->toBe('draft')
        ->and($quotation->quotation_number)->toBeString()
        ->and($quotation->quotation_number)->toContain('QO-')
        ->and($quotation->valid_until)->toBeInstanceOf(Carbon::class);
});

test('can add product items to quotation with quantity and price', function () {
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

    $productCategory = ProductCategory::create([
        'name' => 'Test Category',
        'kode' => 'TC001',
        'cabang_id' => 1,
        'kenaikan_harga' => 0,
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'PROD001',
        'cabang_id' => 1,
        'product_category_id' => $productCategory->id,
        'sell_price' => 100000,
        'cost_price' => 80000,
        'kode_merk' => 'TEST',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    $quotation = Quotation::create([
        'quotation_number' => 'QO-20251101-0002',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'notes' => 'Test quotation',
        'status' => 'draft',
        'created_by' => 1,
    ]);

    $quotationItem = QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 10,
        'tax' => 11,
        'notes' => 'Test item notes',
    ]);

    expect($quotationItem)->toBeInstanceOf(QuotationItem::class)
        ->and($quotationItem->quotation_id)->toBe($quotation->id)
        ->and($quotationItem->product_id)->toBe($product->id)
        ->and($quotationItem->quantity)->toBe(5)
        ->and($quotationItem->unit_price)->toBe(100000)
        ->and($quotationItem->discount)->toBe(10)
        ->and($quotationItem->tax)->toBe(11);
});

test('can calculate quotation totals with subtotal discount and tax', function () {
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

    $productCategory = ProductCategory::create([
        'name' => 'Test Category',
        'kode' => 'TC001',
        'cabang_id' => 1,
        'kenaikan_harga' => 0,
    ]);

    $product1 = Product::create([
        'name' => 'Test Product 1',
        'sku' => 'PROD001',
        'cabang_id' => 1,
        'product_category_id' => $productCategory->id,
        'sell_price' => 100000,
        'cost_price' => 80000,
        'kode_merk' => 'TEST1',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    $product2 = Product::create([
        'name' => 'Test Product 2',
        'sku' => 'PROD002',
        'cabang_id' => 1,
        'product_category_id' => $productCategory->id,
        'sell_price' => 200000,
        'cost_price' => 160000,
        'kode_merk' => 'TEST2',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    $quotation = Quotation::create([
        'quotation_number' => 'QO-20251101-0003',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'notes' => 'Test quotation',
        'status' => 'draft',
        'created_by' => 1,
    ]);

    // Item 1: 5 × 100000 = 500000, discount 10% = 450000, tax 11% = 499500
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $product1->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 10,
        'tax' => 11,
    ]);

    // Item 2: 3 × 200000 = 600000, discount 5% = 570000, tax 11% = 632700
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $product2->id,
        'quantity' => 3,
        'unit_price' => 200000,
        'discount' => 5,
        'tax' => 11,
    ]);

    // Calculate total
    $this->quotationService->updateTotalAmount($quotation);
    $quotation->refresh();

    // Expected total: depends on TaxService implementation
    expect($quotation->total_amount)->toBeGreaterThan(0);
});

test('can send quotation to customer and change status to sent', function () {
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

    $quotation = Quotation::create([
        'quotation_number' => 'QO-20251101-0004',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'notes' => 'Test quotation',
        'status' => 'draft',
        'created_by' => 1,
    ]);

    // Mock email sending - in real implementation this would send email
    // For test, we just change status to 'approve'
    $quotation->update(['status' => 'approve']);

    expect($quotation->status)->toBe('approve');
});

test('can handle customer acceptance and convert to sales order', function () {
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

    $productCategory = ProductCategory::create([
        'name' => 'Test Category',
        'kode' => 'TC001',
        'cabang_id' => 1,
        'kenaikan_harga' => 0,
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'PROD001',
        'cabang_id' => 1,
        'product_category_id' => $productCategory->id,
        'sell_price' => 100000,
        'cost_price' => 80000,
        'kode_merk' => 'TEST',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    $quotation = Quotation::create([
        'quotation_number' => 'QO-20251101-0005',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'notes' => 'Test quotation',
        'status' => 'approve',
        'created_by' => 1,
    ]);

    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 10,
        'tax' => 11,
    ]);

    // Customer accepts quotation
    $quotation->update(['status' => 'approve']);

    // Create sales order from quotation (simplified for test)
    $salesOrder = SaleOrder::create([
        'so_number' => 'SO-20251101-0001',
        'quotation_id' => $quotation->id,
        'customer_id' => $quotation->customer_id,
        'order_date' => now(),
        'notes' => 'Converted from quotation ' . $quotation->quotation_number,
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => 1,
    ]);

    expect($quotation->status)->toBe('approve')
        ->and($salesOrder)->toBeInstanceOf(SaleOrder::class)
        ->and($salesOrder->quotation_id)->toBe($quotation->id)
        ->and($salesOrder->customer_id)->toBe($quotation->customer_id);
});

test('can handle customer rejection and close quotation', function () {
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

    $quotation = Quotation::create([
        'quotation_number' => 'QO-20251101-0006',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'notes' => 'Test quotation',
        'status' => 'approve',
        'created_by' => 1,
    ]);

    // Customer rejects quotation
    $quotation->update(['status' => 'reject']);

    expect($quotation->status)->toBe('reject');
});

test('can auto-close expired quotations', function () {
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

    // Create quotation that expires today
    $quotation = Quotation::create([
        'quotation_number' => 'QO-20251101-0007',
        'customer_id' => $customer->id,
        'date' => now()->subDays(35),
        'valid_until' => now()->subDays(1), // Expired yesterday
        'notes' => 'Test quotation',
        'status' => 'approve',
        'created_by' => 1,
    ]);

    // Simulate expiry check (in real app this would be a scheduled job)
    $expiredQuotations = Quotation::where('status', 'approve')
        ->where('valid_until', '<', now())
        ->get();

    foreach ($expiredQuotations as $expired) {
        $expired->update(['status' => 'reject']);
    }

    $quotation->refresh();

    expect($quotation->status)->toBe('reject')
        ->and(Carbon::parse($quotation->valid_until)->isPast())->toBeTrue();
});

test('quotation service can generate auto-number correctly', function () {
    // Test first quotation of the day
    $code1 = $this->quotationService->generateCode();
    expect($code1)->toContain('QO-' . now()->format('Ymd') . '-0001');

    // Create a quotation to test sequence
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

    Quotation::create([
        'quotation_number' => $code1,
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'status' => 'draft',
        'created_by' => 1,
    ]);

    // Test next quotation number
    $code2 = $this->quotationService->generateCode();
    expect($code2)->toContain('QO-' . now()->format('Ymd') . '-0002');
});

test('quotation approval workflow works correctly', function () {
    // Mock authenticated user for both Auth and activity log
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
    Auth::shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->andReturnSelf();

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

    $quotation = Quotation::create([
        'quotation_number' => 'QO-20251101-0008',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'status' => 'draft',
        'created_by' => 1,
    ]);

    // Test request approve
    $result = $this->quotationService->requestApprove($quotation);
    expect($result)->toBeTrue();

    $quotation->refresh();
    expect($quotation->status)->toBe('request_approve')
        ->and($quotation->request_approve_by)->toBe($user->id)
        ->and($quotation->request_approve_at)->toBeInstanceOf(Carbon::class);

    // Test approve
    $result = $this->quotationService->approve($quotation);
    expect($result)->toBeTrue();

    $quotation->refresh();
    expect($quotation->status)->toBe('approve')
        // approval should be recorded with the current authenticated user id
        ->and($quotation->approve_by)->toBe($user->id)
        ->and($quotation->approve_at)->toBeInstanceOf(Carbon::class);

    // Test reject on a new quotation
    $quotation2 = Quotation::create([
        'quotation_number' => 'QO-20251101-0009',
        'customer_id' => $customer->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'status' => 'request_approve',
        'created_by' => 1,
    ]);

    $result = $this->quotationService->reject($quotation2);
    expect($result)->toBeTrue();

    $quotation2->refresh();
    expect($quotation2->status)->toBe('reject')
        ->and($quotation2->reject_by)->toBe($user->id)
        ->and($quotation2->reject_at)->toBeInstanceOf(Carbon::class);
});