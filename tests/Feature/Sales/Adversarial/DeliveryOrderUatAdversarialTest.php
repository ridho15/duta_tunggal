<?php

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Verifikasi database test terisolasi
    expect(config('database.connections.mysql.database'))->toBe('duta_tunggal_test');

    $this->cabang = Cabang::factory()->create(['nama' => 'Cabang Utama']);

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

    $this->roleGudang = Role::firstOrCreate(['name' => 'Checker Gudang', 'guard_name' => 'web']);
    $this->roleGudang->givePermissionTo($permissions);

    $this->checkerUser = User::factory()->create([
        'cabang_id' => $this->cabang->id,
        'manage_type' => [$this->cabang->id],
    ]);
    $this->checkerUser->assignRole($this->roleGudang);

    // Gudang A & Gudang B
    $this->warehouseA = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'kode' => 'WHA',
        'name' => 'Gudang Utama A',
    ]);

    $this->warehouseB = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'kode' => 'WHB',
        'name' => 'Gudang Satelit B',
    ]);

    // Customer & Product
    $this->customer = Customer::factory()->create([
        'cabang_id' => $this->cabang->id,
        'tipe_pembayaran' => 'Kredit',
        'kredit_limit' => 20000000,
    ]);

    $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'General', 'kode' => 'GEN']);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'product_category_id' => $category->id,
        'sell_price' => 100000,
        'cost_price' => 70000,
        'is_active' => true,
    ]);

    // Menggunakan helper standar stkSetStock untuk mengatur stok fisik tanpa duplikasi baris
    $this->stockA = stkSetStock($this->product, $this->warehouseA, 10.0);
    $this->stockB = stkSetStock($this->product, $this->warehouseB, 10.0);
});

test('ADV-DO-01: Delivery Order store rejects empty or zero total quantity allocation', function () {
    $so = SaleOrder::create([
        'so_number' => 'SO-DO-TEST-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->checkerUser->id,
        'total_amount' => 500000,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $this->product->id,
        'quantity' => 5,
        'delivered_quantity' => 0,
        'unit_price' => 100000,
        'discount' => 0,
        'tipe_pajak' => 'none',
    ]);

    // Request dengan alokasi 0 di seluruh gudang
    $payload = [
        'sale_order_id' => $so->id,
        'items' => [
            [
                'item_id' => $soItem->id,
                'product_id' => $this->product->id,
                'warehouses' => [
                    $this->warehouseA->id => 0,
                    $this->warehouseB->id => 0,
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('penjualan.do.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(DeliveryOrder::count())->toBe(0);
});

test('ADV-DO-02: Delivery Order Splitter automatically separates multi-warehouse allocations into distinct DOs', function () {
    $so = SaleOrder::create([
        'so_number' => 'SO-SPLIT-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->checkerUser->id,
        'total_amount' => 1500000,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $this->product->id,
        'quantity' => 15,
        'delivered_quantity' => 0,
        'unit_price' => 100000,
        'discount' => 0,
        'tipe_pajak' => 'none',
    ]);

    // Alokasi: 8 pcs dari Gudang A, 5 pcs dari Gudang B
    $payload = [
        'sale_order_id' => $so->id,
        'items' => [
            [
                'item_id' => $soItem->id,
                'product_id' => $this->product->id,
                'warehouses' => [
                    $this->warehouseA->id => 8,
                    $this->warehouseB->id => 5,
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('penjualan.do.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // DO harus terpisah menjadi 2 dokumen sesuai gudang
    $dos = DeliveryOrder::whereHas('salesOrders', fn ($q) => $q->where('sale_orders.id', $so->id))->get();
    expect($dos->count())->toBe(2);

    $doA = $dos->firstWhere('warehouse_id', $this->warehouseA->id);
    $doB = $dos->firstWhere('warehouse_id', $this->warehouseB->id);

    expect($doA)->not->toBeNull();
    expect($doB)->not->toBeNull();
    expect((float) $doA->deliveryOrderItem->first()->quantity)->toBe(8.0);
    expect((float) $doB->deliveryOrderItem->first()->quantity)->toBe(5.0);

    // Verifikasi stok fisik terpotong/terikat: Gudang A sisa 2, Gudang B sisa 5
    $this->stockA->refresh();
    $this->stockB->refresh();
    expect((float) $this->stockA->qty_available)->toBe(2.0);
    expect((float) $this->stockB->qty_available)->toBe(5.0);
});

test('ADV-DO-03: Illegal status jump directly from request_stock to completed is rejected', function () {
    $do = DeliveryOrder::create([
        'do_number' => 'DO-JUMP-' . uniqid(),
        'delivery_date' => now(),
        'warehouse_id' => $this->warehouseA->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'request_stock',
        'created_by' => $this->checkerUser->id,
    ]);

    expect(function () use ($do) {
        app(DeliveryOrderTransitions::class)->to($do, 'completed');
    })->toThrow(DeliveryOrderTransitionException::class);

    expect($do->fresh()->status)->toBe('request_stock');
});

test('ADV-DO-04: Unauthorized cancellation of DO in sent status is strictly blocked', function () {
    $do = DeliveryOrder::create([
        'do_number' => 'DO-SENT-' . uniqid(),
        'delivery_date' => now(),
        'warehouse_id' => $this->warehouseA->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'sent',
        'created_by' => $this->checkerUser->id,
    ]);

    expect(function () use ($do) {
        app(DeliveryOrderTransitions::class)->to($do, 'reject');
    })->toThrow(DeliveryOrderTransitionException::class);

    expect($do->fresh()->status)->toBe('sent');
});

test('UAT-DO-01: Checker creates valid DO with 4-Layer Data Validation', function () {
    $so = SaleOrder::create([
        'so_number' => 'SO-VALID-' . uniqid(),
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->currency->id,
        'created_by' => $this->checkerUser->id,
        'total_amount' => 600000,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $this->product->id,
        'quantity' => 6,
        'delivered_quantity' => 0,
        'unit_price' => 100000,
        'discount' => 0,
        'tipe_pajak' => 'none',
    ]);

    $payload = [
        'sale_order_id' => $so->id,
        'items' => [
            [
                'item_id' => $soItem->id,
                'product_id' => $this->product->id,
                'warehouses' => [
                    $this->warehouseA->id => 6,
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('penjualan.do.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $do = DeliveryOrder::where('warehouse_id', $this->warehouseA->id)->latest()->first();

    // LAYER 1: Dokumen DO terbuat sah
    expect($do)->not->toBeNull();
    expect($do->deliveryOrderItem->count())->toBe(1);
    expect((float) $do->deliveryOrderItem->first()->quantity)->toBe(6.0);

    // LAYER 2: Hubungan ke SO tercatat & stok fisik terpotong (10 - 6 = 4)
    expect($do->salesOrders()->where('sale_orders.id', $so->id)->exists())->toBeTrue();
    $this->stockA->refresh();
    expect((float) $this->stockA->qty_available)->toBe(4.0);

    // LAYER 3: Jurnal Akuntansi (Belum ada jurnal saat DO dibuat, jurnal baru saat SJ terbit / Delivered)
    expect(DB::table('journal_entries')->where('source_type', DeliveryOrder::class)->where('source_id', $do->id)->count())->toBe(0);

    // LAYER 4: Kartu Piutang (Faktur belum diterbitkan)
    expect(DB::table('account_receivables')->where('customer_id', $this->customer->id)->count())->toBe(0);
});
