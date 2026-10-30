<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemCheck;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ReturnProduct;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(config('database.connections.mysql.database'))->toBe('duta_tunggal_test');

    $this->cabang = Cabang::factory()->create(['nama' => 'Cabang Checker']);

    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    // Role Checker Gudang
    $permissions = [
        'view any delivery order',
        'view delivery order',
        'create delivery order',
        'update delivery order',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->roleChecker = Role::firstOrCreate(['name' => 'Checker Gudang', 'guard_name' => 'web']);
    $this->roleChecker->givePermissionTo($permissions);

    $this->checkerUser = User::factory()->create([
        'cabang_id' => $this->cabang->id,
        'manage_type' => [$this->cabang->id],
    ]);
    $this->checkerUser->assignRole($this->roleChecker);

    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'kode' => 'WHC',
        'name' => 'Gudang Checker',
    ]);

    $this->customer = Customer::factory()->create([
        'cabang_id' => $this->cabang->id,
        'tipe_pembayaran' => 'Kredit',
        'kredit_limit' => 20000000,
    ]);

    $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Checker Category', 'kode' => 'CHK']);

    $this->product1 = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'product_category_id' => $category->id,
        'name' => 'Produk Uji Checker 1',
        'sell_price' => 50000,
        'cost_price' => 35000,
        'is_active' => true,
    ]);

    $this->product2 = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'product_category_id' => $category->id,
        'name' => 'Produk Uji Checker 2',
        'sell_price' => 80000,
        'cost_price' => 60000,
        'is_active' => true,
    ]);

    stkSetStock($this->product1, $this->warehouse, 50.0);
    stkSetStock($this->product2, $this->warehouse, 50.0);
});

test('ADV-CHK-01: Checker cannot confirm shipment if not all items are checked', function () {
    $do = DeliveryOrder::create([
        'do_number' => 'DO-CHK-01-' . uniqid(),
        'delivery_date' => now(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
        'created_by' => $this->checkerUser->id,
    ]);

    $item1 = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product1->id,
        'quantity' => 10,
    ]);

    $item2 = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product2->id,
        'quantity' => 5,
    ]);

    // Checker hanya mencentang item1, item2 tidak dicentang
    $payload = [
        'checked_items' => [(string) $item1->id],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.kiriman.submitCheck', $do->id), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($do->fresh()->status)->toBe('approved');
});

test('ADV-CHK-02: DO Return rejects short + reject quantity exceeding DO item quantity', function () {
    $do = DeliveryOrder::create([
        'do_number' => 'DO-CHK-02-' . uniqid(),
        'delivery_date' => now(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
        'created_by' => $this->checkerUser->id,
    ]);

    $item = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product1->id,
        'quantity' => 5, // Qty DO = 5
    ]);

    // Checker melaporkan kurang 4 + reject 3 = 7 (melebihi 5)
    $payload = [
        'items' => [
            $item->id => [
                'kurang' => 4,
                'reject' => 3,
            ],
        ],
        'notes' => 'Uji melebihi kuantitas DO',
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.kiriman.submitRetur', $do->id), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
    expect(ReturnProduct::count())->toBe(0);
});

test('ADV-CHK-03: DO Return is strictly blocked if DO already has an active Surat Jalan', function () {
    $do = DeliveryOrder::create([
        'do_number' => 'DO-CHK-03-' . uniqid(),
        'delivery_date' => now(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'confirmed',
        'created_by' => $this->checkerUser->id,
    ]);

    $item = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product1->id,
        'quantity' => 10,
    ]);

    // Buat Surat Jalan terbit untuk DO ini
    $sj = SuratJalan::create([
        'sj_number' => 'SJ-CHK-03-' . uniqid(),
        'status' => SuratJalan::STATUS_ISSUED,
        'issued_at' => now(),
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->checkerUser->id,
    ]);
    $sj->deliveryOrder()->attach($do->id);

    $payload = [
        'items' => [
            $item->id => [
                'kurang' => 2,
                'reject' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.kiriman.submitRetur', $do->id), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
    expect(ReturnProduct::count())->toBe(0);
});

test('UAT-CHK-01: Checker completes full physical check (All Items OK -> DO Confirmed)', function () {
    $do = DeliveryOrder::create([
        'do_number' => 'DO-CHK-UAT1-' . uniqid(),
        'delivery_date' => now(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
        'created_by' => $this->checkerUser->id,
    ]);

    $item1 = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product1->id,
        'quantity' => 10,
    ]);

    $item2 = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product2->id,
        'quantity' => 5,
    ]);

    // Checker mencentang semua item secara riil
    $payload = [
        'checked_items' => [(string) $item1->id, (string) $item2->id],
        'notes' => 'Pengecekan fisik selesai, barang lengkap dan bagus.',
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.kiriman.submitCheck', $do->id), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $do->refresh();

    // LAYER 1: Dokumen DO terkonfirmasi siap SJ
    expect($do->status)->toBe('confirmed');
    expect(DeliveryOrderItemCheck::where('delivery_order_item_id', $item1->id)->count())->toBe(1);
    expect(DeliveryOrderItemCheck::where('delivery_order_item_id', $item2->id)->count())->toBe(1);

    // LAYER 2: Stok Gudang tidak berubah (sudah terpotong saat DO dibuat)
    // LAYER 3: Belum ada jurnal timbul
    expect(DB::table('journal_entries')->where('source_type', DeliveryOrder::class)->where('source_id', $do->id)->count())->toBe(0);

    // LAYER 4: Belum ada piutang
    expect(DB::table('account_receivables')->where('customer_id', $this->customer->id)->count())->toBe(0);
});

test('UAT-CHK-02: Checker logs discrepancy (Shortage & Damage) creating draft ReturnProduct DO.R', function () {
    $do = DeliveryOrder::create([
        'do_number' => 'DO-CHK-UAT2-' . uniqid(),
        'delivery_date' => now(),
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
        'created_by' => $this->checkerUser->id,
    ]);

    $item = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $this->product1->id,
        'quantity' => 10,
    ]);

    // Checker melaporkan 2 kurang dan 1 rusak
    $payload = [
        'items' => [
            $item->id => [
                'kurang' => 2,
                'reject' => 1,
            ],
        ],
        'notes' => 'Ada 2 kurang dari packing pabrik, 1 retak.',
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.kiriman.submitRetur', $do->id), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Dokumen Retur DO terbentuk dengan status draft
    $retur = ReturnProduct::where('from_model_type', DeliveryOrder::class)
        ->where('from_model_id', $do->id)
        ->first();

    expect($retur)->not->toBeNull();
    expect($retur->status)->toBe('draft');
    expect($retur->returnProductItems()->count())->toBe(2); // 1 baris good (kurang) + 1 baris damage (rusak)
});
