<?php

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(config('database.connections.mysql.database'))->toBe('duta_tunggal_test');

    $this->cabang1 = Cabang::factory()->create(['nama' => 'Cabang Jakarta']);
    $this->cabang2 = Cabang::factory()->create(['nama' => 'Cabang Surabaya']);

    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    // Setup Akun COA Standar untuk Jurnal Penjualan
    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        [
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'currency_id' => $this->currency->id,
            'cabang_id' => $this->cabang1->id,
        ]
    );

    $this->arCoa            = $coa('1120', 'Piutang Usaha', 'Asset');
    $this->revCoa           = $coa('4000', 'Pendapatan Penjualan', 'Revenue');
    $this->cogsCoa          = $coa('5000', 'Beban Pokok Penjualan', 'Expense');
    $this->invCoa           = $coa('1140', 'Persediaan Barang Dagang', 'Asset');
    $this->goodsDeliveryCoa = $coa('1140.20', 'Pengiriman Barang Belum Ditagih', 'Asset');
    $this->ppnCoa           = $coa('2150', 'PPN Keluaran', 'Liability');

    // Buat Permission dan Role
    $pSjCreate = Permission::firstOrCreate(['name' => 'surat_jalan_create', 'guard_name' => 'web']);
    $pSjCancel = Permission::firstOrCreate(['name' => 'surat_jalan_cancel', 'guard_name' => 'web']);
    $pInvCreate = Permission::firstOrCreate(['name' => 'invoice_create', 'guard_name' => 'web']);
    $pInvEdit = Permission::firstOrCreate(['name' => 'invoice_edit', 'guard_name' => 'web']);
    $pInvView = Permission::firstOrCreate(['name' => 'invoice_view', 'guard_name' => 'web']);

    $this->roleShipping = Role::firstOrCreate(['name' => 'Admin Pengiriman', 'guard_name' => 'web']);
    $this->roleShipping->syncPermissions([$pSjCreate, $pSjCancel]);

    $this->roleAr = Role::firstOrCreate(['name' => 'Invoicing / AR', 'guard_name' => 'web']);
    $this->roleAr->syncPermissions([$pInvCreate, $pInvEdit, $pInvView]);

    $this->shippingUser = User::factory()->create([
        'name' => 'Staf Logistik',
        'cabang_id' => $this->cabang1->id,
    ]);
    $this->shippingUser->assignRole($this->roleShipping);

    $this->arUser = User::factory()->create([
        'name' => 'Akuntan AR',
        'cabang_id' => $this->cabang1->id,
    ]);
    $this->arUser->assignRole($this->roleAr);

    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'kode' => 'WH-JKT',
        'name' => 'Gudang Jakarta',
    ]);

    $this->driver = Driver::factory()->create(['name' => 'Pak Joko', 'cabang_id' => $this->cabang1->id]);
    $this->vehicle = Vehicle::factory()->create(['plate' => 'B 9999 DT', 'cabang_id' => $this->cabang1->id]);

    $this->customer = Customer::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'name' => 'PT Mitra Sentosa Abadi',
        'tipe_pembayaran' => 'Kredit',
        'kredit_limit' => 50000000,
    ]);

    $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Material', 'kode' => 'MAT']);
    $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Pieces', 'abbreviation' => 'pcs']);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang1->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'name' => 'Pipa Tembaga 1/2',
        'sell_price' => 100000,
        'cost_price' => 70000,
        'sales_coa_id' => $this->revCoa->id,
        'cogs_coa_id' => $this->cogsCoa->id,
        'inventory_coa_id' => $this->invCoa->id,
        'goods_delivery_coa_id' => $this->goodsDeliveryCoa->id,
        'is_active' => true,
    ]);

    stkSetStock($this->product, $this->warehouse, 100.0);
});

test('ADV-SJ-01: Merging Delivery Orders from different branches into one Surat Jalan is strictly rejected', function () {
    // HQ user dengan akses multi-cabang mencoba menggabungkan DO dari cabang Jakarta dan Surabaya
    $this->shippingUser->update(['manage_type' => ['all']]);

    $soCabang1 = SaleOrder::create([
        'so_number' => 'SO-CBG1-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->shippingUser->id,
        'total_amount' => 1000000,
    ]);

    $doCabang1 = DeliveryOrder::create([
        'do_number' => 'DO-CBG1-' . uniqid(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang1->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $this->shippingUser->id,
    ]);
    $doCabang1->salesOrders()->attach($soCabang1->id);

    $warehouse2 = Warehouse::factory()->create([
        'cabang_id' => $this->cabang2->id,
        'kode' => 'WH-SBY',
        'name' => 'Gudang Surabaya',
    ]);

    $doCabang2 = DeliveryOrder::create([
        'do_number' => 'DO-CBG2-' . uniqid(),
        'warehouse_id' => $warehouse2->id,
        'cabang_id' => $this->cabang2->id, // Cabang Berbeda!
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $this->shippingUser->id,
    ]);
    $doCabang2->salesOrders()->attach($soCabang1->id);

    $payload = [
        'delivery_order_ids' => [$doCabang1->id, $doCabang2->id],
        'issued_at' => now()->toDateString(),
        'delivery_method' => 'internal',
        'driver_id' => $this->driver->id,
        'vehicle_id' => $this->vehicle->id,
    ];

    $response = $this->actingAs($this->shippingUser)->post(route('penjualan.sj.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['deliveryOrder']);
    expect(SuratJalan::count())->toBe(0);
});

test('ADV-SJ-02: Cancelling Surat Jalan requires a valid reason with minimum 5 characters', function () {
    $sj = SuratJalan::create([
        'sj_number' => 'SJ-CANCEL-' . uniqid(),
        'status' => SuratJalan::STATUS_ISSUED,
        'issued_at' => now(),
        'cabang_id' => $this->cabang1->id,
        'created_by' => $this->shippingUser->id,
    ]);

    // Alasan kosong atau < 5 karakter
    $payload = [
        'cancel_reason' => 'bad', // < 5 karakter
    ];

    $response = $this->actingAs($this->shippingUser)->post(route('penjualan.sj.cancel', $sj->id), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['cancel_reason']);
    expect($sj->fresh()->status)->toBe(SuratJalan::STATUS_ISSUED);
});

test('ADV-INV-01: Creating Invoice from non-issued Surat Jalan is strictly rejected', function () {
    $sjDraft = SuratJalan::create([
        'sj_number' => 'SJ-DRAFT-' . uniqid(),
        'status' => SuratJalan::STATUS_DRAFT, // Draft, belum resmi terbit
        'issued_at' => now(),
        'cabang_id' => $this->cabang1->id,
        'created_by' => $this->shippingUser->id,
    ]);

    $payload = [
        'surat_jalan_ids' => [$sjDraft->id],
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
    ];

    $response = $this->actingAs($this->arUser)->post(route('penjualan.invoices.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['surat_jalan_ids']);
    expect(Invoice::count())->toBe(0);
});

test('ADV-INV-02: Direct edit of posted Invoice is locked and redirects with audit notice', function () {
    $so = SaleOrder::create([
        'so_number' => 'SO-INV-LOCK-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->shippingUser->id,
        'total_amount' => 1000000,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-LOCK-' . uniqid(),
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'customer_name' => $this->customer->name,
        'cabang_id' => $this->cabang1->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 1000000,
        'total' => 1000000,
    ]);

    $response = $this->actingAs($this->arUser)->get(route('penjualan.invoices.edit', $invoice->id));

    $response->assertRedirect(route('penjualan.invoices.show', $invoice->id));
    $response->assertSessionHas('error');
});

test('UAT-SJ-INV-01: Multi-DO Surat Jalan to Sales Invoice with 4-Layer Balanced Ledger Validation', function () {
    $so = SaleOrder::create([
        'so_number' => 'SO-SJ-INV-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang1->id,
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->shippingUser->id,
        'total_amount' => 1500000,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $this->product->id,
        'quantity' => 15,
        'delivered_quantity' => 0, // Belum terkirim sebelum DO & SJ
        'unit_price' => 100000,
        'discount' => 0,
        'tipe_pajak' => 'none',
    ]);

    // 2 DO kolektif untuk 1 customer
    $do1 = DeliveryOrder::create([
        'do_number' => 'DO-KOL-1-' . uniqid(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang1->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $this->shippingUser->id,
    ]);
    $do1->salesOrders()->attach($so->id);
    $doItem1 = DeliveryOrderItem::create([
        'delivery_order_id' => $do1->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem1->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
    ]);

    $do2 = DeliveryOrder::create([
        'do_number' => 'DO-KOL-2-' . uniqid(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang1->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $this->shippingUser->id,
    ]);
    $do2->salesOrders()->attach($so->id);
    $doItem2 = DeliveryOrderItem::create([
        'delivery_order_id' => $do2->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $this->product->id,
        'quantity' => 5,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem2->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
    ]);

    // LANGKAH 1: Admin Pengiriman menerbitkan Surat Jalan Kolektif Multi-DO
    $sjPayload = [
        'delivery_order_ids' => [$do1->id, $do2->id],
        'issued_at' => now()->toDateString(),
        'delivery_method' => 'internal',
        'driver_id' => $this->driver->id,
        'vehicle_id' => $this->vehicle->id,
        'notes' => 'Pengiriman armada internal gabungan DO 1 dan DO 2',
    ];

    $sjResponse = $this->actingAs($this->shippingUser)->post(route('penjualan.sj.store'), $sjPayload);
    $sjResponse->assertRedirect();
    $sjResponse->assertSessionHas('success');

    $sj = SuratJalan::latest()->first();
    expect($sj)->not->toBeNull();
    expect($sj->status)->toBe(SuratJalan::STATUS_ISSUED);
    expect($sj->deliveryOrder->count())->toBe(2);

    // Otomatis DO bertransisi ke received/completed
    expect($do1->fresh()->status)->toBe('received');
    expect($do2->fresh()->status)->toBe('received');

    // LANGKAH 2: Akuntan AR menerbitkan Faktur Penjualan resmi
    $invPayload = [
        'surat_jalan_ids' => [$sj->id],
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'cabang_id' => $this->cabang1->id,
        'notes' => 'Faktur resmi dari Surat Jalan Kolektif',
    ];

    $invResponse = $this->actingAs($this->arUser)->post(route('penjualan.invoices.store'), $invPayload);
    $invResponse->assertRedirect();
    $invResponse->assertSessionHas('success');

    $invoice = Invoice::latest()->first();

    // LAYER 1: Dokumen Faktur Terbit
    expect($invoice)->not->toBeNull();
    expect((float) $invoice->total)->toBe(1500000.0);
    expect($invoice->status)->toBe(Invoice::STATUS_SENT);

    // LAYER 2: Stok Gudang Konsisten
    expect((float) $this->product->inventoryStock()->sum('qty_available'))->toBe(85.0); // 100 - 15 = 85

    // LAYER 3: Jurnal Akuntansi Penjualan Seimbang Mutlak (Zero Balance Drift)
    // Termasuk 1.500.000 Piutang/Pendapatan + 1.050.000 HPP/Persediaan = 2.550.000
    $journals = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();

    expect($journals->count())->toBeGreaterThan(0);
    $totalDebit = (float) $journals->sum('debit');
    $totalCredit = (float) $journals->sum('credit');
    expect($totalDebit)->toBe($totalCredit);
    expect($totalDebit)->toBe(2550000.0);

    // Verifikasi spesifik akun Piutang Usaha (1120) & Pendapatan Penjualan (4000)
    $arEntry = $journals->where('coa_id', $this->arCoa->id)->first();
    expect($arEntry)->not->toBeNull();
    expect((float) $arEntry->debit)->toBe(1500000.0);

    $revTotalCredit = (float) $journals->where('coa_id', $this->revCoa->id)->sum('credit');
    expect($revTotalCredit)->toBe(1500000.0);

    // LAYER 4: Kartu Piutang (AR) Terbentuk Sempurna
    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect($ar)->not->toBeNull();
    expect((float) $ar->total)->toBe(1500000.0);
    expect((float) $ar->remaining)->toBe(1500000.0);
    expect((float) $ar->paid)->toBe(0.0);
});
