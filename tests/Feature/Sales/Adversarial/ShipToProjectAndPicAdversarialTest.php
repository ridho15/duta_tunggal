<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryOrderSourceValidator;
use App\Services\SalesOrderService;
use App\Services\SuratJalanDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(config('database.connections.mysql.database'))->toBe('duta_tunggal_test');

    $this->cabang1 = Cabang::factory()->create(['nama' => 'Cabang Utama Bandung']);
    $this->cabang2 = Cabang::factory()->create(['nama' => 'Cabang Surabaya']);

    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    $permissions = [
        'view any sales order',
        'view sales order',
        'create sales order',
        'update sales order',
        'delete sales order',
        'view any quotation',
        'view quotation',
        'create quotation',
        'update quotation',
        'view any delivery order',
        'view delivery order',
        'create delivery order',
        'view any surat jalan',
        'view surat jalan',
        'create surat jalan',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->roleSales = Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
    $this->roleSales->givePermissionTo($permissions);

    $this->salesUser = User::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'manage_type' => [$this->cabang1->id],
    ]);
    $this->salesUser->assignRole($this->roleSales);

    $this->otherBranchUser = User::factory()->create([
        'cabang_id' => $this->cabang2->id,
        'manage_type' => [$this->cabang2->id],
    ]);
    $this->otherBranchUser->assignRole($this->roleSales);

    $this->customer = Customer::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'name' => 'PT CITRA TEKNIK MEDICA',
        'perusahaan' => 'PT CITRA TEKNIK MEDICA',
        'phone' => '022-7201234',
        'telephone' => '022-7201234',
        'address' => 'Jl. Peta No. 226 Bandung',
        'kredit_limit' => 50000000,
        'tipe_pembayaran' => 'Kredit',
        'tempo_kredit' => 30,
    ]);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'sell_price' => 150000,
        'is_active' => true,
    ]);
});

test('HP-01: SO created with same_as_customer inherits customer details and address', function () {
    $this->actingAs($this->salesUser);

    $payload = [
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'delivery_date' => now()->addDays(2)->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'same_as_customer' => '1',
        'shipped_to' => '',
        'shipping_recipient_name' => '',
        'shipping_recipient_phone' => '',
        'currency_id' => $this->currency->id,
        'tempo_pembayaran' => 30,
        'action' => 'draft',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 5,
                'unit_price' => 150000,
                'tax_type' => 'None',
            ],
        ],
    ];

    $response = $this->post(route('penjualan.so.store'), $payload);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $so = SaleOrder::latest('id')->first();
    expect($so)->not->toBeNull()
        ->and($so->customer_id)->toBe($this->customer->id)
        ->and($so->recipientName())->toBe('PT CITRA TEKNIK MEDICA')
        ->and($so->recipientPhone())->toBe('022-7201234')
        ->and($so->shippingAddress())->toBe('Jl. Peta No. 226 Bandung');
});

test('HP-02: SO created with separate project Ship-To details outputs correctly to Surat Jalan PDF', function () {
    $this->actingAs($this->salesUser);

    $payload = [
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'delivery_date' => now()->addDays(3)->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'same_as_customer' => '0',
        'shipping_recipient_name' => 'Proyek RSUD Cibabat Gedung B',
        'shipping_recipient_phone' => 'Pak Dedi (0812-3456-7890)',
        'shipped_to' => 'Jl. Jend. Sudirman No. 120 Cimahi',
        'currency_id' => $this->currency->id,
        'tempo_pembayaran' => 30,
        'action' => 'draft',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 10,
                'unit_price' => 150000,
                'tax_type' => 'None',
            ],
        ],
    ];

    $response = $this->post(route('penjualan.so.store'), $payload);
    $response->assertSessionHasNoErrors();

    $so = SaleOrder::latest('id')->first();
    expect($so->shipping_recipient_name)->toBe('Proyek RSUD Cibabat Gedung B')
        ->and($so->shipping_recipient_phone)->toBe('Pak Dedi (0812-3456-7890)')
        ->and($so->shipped_to)->toBe('Jl. Jend. Sudirman No. 120 Cimahi')
        ->and($so->recipientName())->toBe('Proyek RSUD Cibabat Gedung B')
        ->and($so->recipientPhone())->toBe('Pak Dedi (0812-3456-7890)')
        ->and($so->shippingAddress())->toBe('Jl. Jend. Sudirman No. 120 Cimahi');

    // Create Warehouse, DO, and SJ linked to this SO to test SuratJalanDocumentBuilder
    $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang1->id]);
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $do = DeliveryOrder::create([
        'do_number' => 'DO-TEST-001',
        'cabang_id' => $this->cabang1->id,
        'customer_id' => $this->customer->id,
        'delivery_date' => now()->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $this->salesUser->id,
    ]);
    $do->salesOrders()->attach($so->id);

    $soItem = $so->items()->first();
    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product->id,
        'sale_order_item_id' => $soItem->id,
        'quantity' => 10,
        'unit_price' => 150000,
    ]);

    $sj = SuratJalan::create([
        'sj_number' => 'SJ-TEST-001',
        'cabang_id' => $this->cabang1->id,
        'delivery_order_id' => $do->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'issued_at' => now(),
        'status' => SuratJalan::STATUS_DRAFT,
        'created_by' => $this->salesUser->id,
    ]);
    $sj->deliveryOrder()->attach($do->id);

    $builder = app(SuratJalanDocumentBuilder::class);
    $doc = $builder->build($sj);

    // Destination name must include the project recipient name and PIC phone
    expect($doc['delivery']['destination_name'])->toContain('Proyek RSUD Cibabat Gedung B')
        ->and($doc['delivery']['destination_name'])->toContain('Pak Dedi (0812-3456-7890)')
        ->and($doc['delivery']['destination_address'])->toBe('Jl. Jend. Sudirman No. 120 Cimahi');

    // Verify PDF template renders without throwing syntax or variable errors
    $pdfView = view('pdf.surat-jalan', ['doc' => $doc])->render();
    expect($pdfView)->toContain('Proyek RSUD Cibabat Gedung B')
        ->and($pdfView)->toContain('Pak Dedi (0812-3456-7890)')
        ->and($pdfView)->toContain('Jl. Jend. Sudirman No. 120 Cimahi')
        ->and($pdfView)->toContain('PT CITRA TEKNIK MEDICA'); // Buyer company name also rendered
});

test('UP-01: rejects extreme string length and prevents malformed recipient inputs', function () {
    $this->actingAs($this->salesUser);

    $tooLongName = str_repeat('A', 151); // Max is 150
    $tooLongPhone = str_repeat('0', 51); // Max is 50

    $payload = [
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipping_recipient_name' => $tooLongName,
        'shipping_recipient_phone' => $tooLongPhone,
        'shipped_to' => 'Alamat Valid',
        'currency_id' => $this->currency->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 100000,
            ],
        ],
    ];

    $response = $this->post(route('penjualan.so.store'), $payload);
    $response->assertSessionHasErrors(['shipping_recipient_name', 'shipping_recipient_phone']);

    // Ensure database remained clean
    expect(SaleOrder::where('shipping_recipient_name', $tooLongName)->exists())->toBeFalse();
});

test('UP-02: empty or whitespace recipient input falls back safely without null pointer crashes', function () {
    $this->actingAs($this->salesUser);

    $so = SaleOrder::create([
        'so_number' => 'SO-FALLBACK-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipping_recipient_name' => '   ', // whitespace
        'shipping_recipient_phone' => null,
        'shipped_to' => null,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'created_by' => $this->salesUser->id,
    ]);

    // Test helper methods resilience
    expect($so->recipientName())->toBe('PT CITRA TEKNIK MEDICA')
        ->and($so->recipientPhone())->toBe('022-7201234')
        ->and($so->shippingAddress())->toBe('Jl. Peta No. 226 Bandung');

    // Test blade view rendering via real HTTP controller endpoint
    $response = $this->get(route('penjualan.so.show', $so->id));
    $response->assertOk();
    $response->assertSee('PT CITRA TEKNIK MEDICA');
    $response->assertSee('Jl. Peta No. 226 Bandung');
});

test('UP-03: cross-branch isolation prevents unauthorized branch customer and project assignment', function () {
    $this->actingAs($this->otherBranchUser); // Branch 2 user

    // Customer belongs to Branch 1
    $payload = [
        'customer_id' => $this->customer->id, // Cabang 1
        'cabang_id' => $this->cabang2->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipping_recipient_name' => 'Hacked Cross-Branch Project',
        'shipped_to' => 'Jl. Cross Branch',
        'currency_id' => $this->currency->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 100000,
            ],
        ],
    ];

    $response = $this->post(route('penjualan.so.store'), $payload);
    // Either fails validation due to customer scope or redirects with error
    expect(SaleOrder::where('shipping_recipient_name', 'Hacked Cross-Branch Project')->exists())->toBeFalse();
});

test('UP-04: blocks merging 2 SOs into 1 DO when delivery destinations differ', function () {
    $so1 = SaleOrder::create([
        'so_number' => 'SO-DEST-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Jl. Proyek A Bandung',
        'currency_id' => $this->currency->id,
        'status' => 'approved',
        'created_by' => $this->salesUser->id,
    ]);

    $so2 = SaleOrder::create([
        'so_number' => 'SO-DEST-002',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Jl. Proyek B Cimahi',
        'currency_id' => $this->currency->id,
        'status' => 'approved',
        'created_by' => $this->salesUser->id,
    ]);

    $validator = app(DeliveryOrderSourceValidator::class);
    $errors = $validator->mixErrors([$so1->id, $so2->id]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('harus memiliki alamat kirim yang sama');
});

test('UP-05: Quotation with custom project ship-to seamlessly converts to SO without data loss', function () {
    $this->actingAs($this->salesUser);

    $quotation = Quotation::create([
        'quotation_number' => 'QUO-PROJECT-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'currency_id' => $this->currency->id,
        'date' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(14)->format('Y-m-d'),
        'shipping_recipient_name' => 'Proyek Bandara Kertajati Terminal 2',
        'shipping_recipient_phone' => 'Ir. Bambang (0811-2233-4455)',
        'shipped_to' => 'Kertajati, Kabupaten Majalengka',
        'status' => Quotation::STATUS_APPROVE,
        'subtotal' => 300000,
        'grand_total' => 300000,
        'created_by' => $this->salesUser->id,
    ]);

    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 150000,
        'subtotal' => 300000,
    ]);

    // 1. Service headerFromQuotation verification
    $service = app(SalesOrderService::class);
    $headerData = $service->headerFromQuotation($quotation);

    expect($headerData['shipping_recipient_name'])->toBe('Proyek Bandara Kertajati Terminal 2')
        ->and($headerData['shipping_recipient_phone'])->toBe('Ir. Bambang (0811-2233-4455)')
        ->and($headerData['shipped_to'])->toBe('Kertajati, Kabupaten Majalengka');

    // 2. API endpoint verification (used by dynamic frontend form)
    $apiResponse = $this->getJson(route('api.sales-orders.quotation', $quotation->id));
    $apiResponse->assertOk()
        ->assertJsonPath('data.shipping_recipient_name', 'Proyek Bandara Kertajati Terminal 2')
        ->assertJsonPath('data.shipping_recipient_phone', 'Ir. Bambang (0811-2233-4455)')
        ->assertJsonPath('data.shipped_to', 'Kertajati, Kabupaten Majalengka');

    // 3. Post to SO store using the quotation data
    $soPayload = [
        'customer_id' => $headerData['customer_id'],
        'quotation_id' => $quotation->id,
        'cabang_id' => $headerData['cabang_id'],
        'currency_id' => $headerData['currency_id'],
        'exchange_rate' => $headerData['exchange_rate'],
        'tempo_pembayaran' => $headerData['tempo_pembayaran'],
        'shipped_to' => $headerData['shipped_to'],
        'shipping_recipient_name' => $headerData['shipping_recipient_name'],
        'shipping_recipient_phone' => $headerData['shipping_recipient_phone'],
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'action' => 'draft',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 150000,
                'tax_type' => 'None',
            ],
        ],
    ];

    $response = $this->post(route('penjualan.so.store'), $soPayload);
    $response->assertSessionHasNoErrors();

    $createdSo = SaleOrder::where('quotation_id', $quotation->id)->first();
    expect($createdSo)->not->toBeNull()
        ->and($createdSo->shipping_recipient_name)->toBe('Proyek Bandara Kertajati Terminal 2')
        ->and($createdSo->shipping_recipient_phone)->toBe('Ir. Bambang (0811-2233-4455)')
        ->and($createdSo->shipped_to)->toBe('Kertajati, Kabupaten Majalengka')
        ->and($createdSo->recipientName())->toBe('Proyek Bandara Kertajati Terminal 2');
});
