<?php

/**
 * Fixture bersama untuk tes stok & pengiriman (T2).
 *
 * Kenapa perlu: `Product::created` otomatis membuat baris `InventoryStock` bernilai 0 (rak null) untuk SETIAP gudang yang ada;
 * tes lama yang memanggil `InventoryStock::create(...)` menghasilkan baris KEDUA sehingga layanan yang membaca baris pertama
 * melihat "Tersedia: 0" (akar gagalnya StockReservation*Test). Helper ini selalu mengatur SATU baris per produk×gudang.
 * Semua fungsi berawalan `stk` agar tidak bentrok dengan helper tes lain.
 */

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockReservation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/** Konteks dasar: cabang, pengguna (semua cabang), gudang, akun untuk jurnal invoice/DO, customer, satu produk. */
function stkContext(array $overrides = []): array
{
    $cabang = Cabang::factory()->create(['kode' => 'STK-'.strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang Stok', 'status' => 1, 'lihat_stok_cabang_lain' => false]);
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    Auth::login($user);

    $idr = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'kode' => 'G-'.strtoupper(substr(uniqid(), -5)), 'name' => 'Gudang Utama', 'status' => 1]);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    $coa('1120', 'Piutang Dagang', 'Asset');
    $coa('1112.01', 'Kas dan Bank', 'Asset');
    $revenue = $coa('4000', 'Penjualan', 'Revenue');
    $coa('2120.06', 'PPn Keluaran', 'Liability');
    $cogs = $coa('5100.10', 'HPP', 'Expense');
    $goods = $coa('1140.20', 'Barang Terkirim', 'Asset');
    $inventory = $coa('1140.10', 'Persediaan', 'Asset');
    $coa('4120.10', 'Retur Penjualan', 'Revenue');
    $coa('6100.02', 'Biaya Pengiriman', 'Expense');

    $uom = UnitOfMeasure::factory()->create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'name' => 'PT Uji Stok', 'tempo_kredit' => 30, 'tipe_pembayaran' => 'Bebas']);
    $product = Product::factory()->create([
        'sku' => 'STK-'.strtoupper(substr(uniqid(), -6)), 'name' => 'Alat Uji Stok', 'uom_id' => $uom->id, 'cost_price' => 5000, 'sell_price' => 10000,
        'sales_coa_id' => $revenue->id, 'cogs_coa_id' => $cogs->id, 'goods_delivery_coa_id' => $goods->id, 'inventory_coa_id' => $inventory->id,
    ]);

    return array_merge(compact('cabang', 'user', 'idr', 'warehouse', 'customer', 'product'), $overrides);
}

/** Gudang tambahan (cabang yang sama secara default). */
function stkWarehouse(array $ctx, string $name = 'Gudang Lain', ?Cabang $cabang = null): Warehouse
{
    return Warehouse::factory()->create([
        'cabang_id' => ($cabang ?? $ctx['cabang'])->id, 'kode' => 'G-'.strtoupper(substr(uniqid(), -5)), 'name' => $name, 'status' => 1,
    ]);
}

/** Produk tambahan dengan akun yang sama. */
function stkProduct(array $ctx, array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'sku' => 'STK-'.strtoupper(substr(uniqid(), -6)), 'cost_price' => 5000, 'sell_price' => 10000, 'uom_id' => $ctx['product']->uom_id,
        'sales_coa_id' => $ctx['product']->sales_coa_id, 'cogs_coa_id' => $ctx['product']->cogs_coa_id,
        'goods_delivery_coa_id' => $ctx['product']->goods_delivery_coa_id, 'inventory_coa_id' => $ctx['product']->inventory_coa_id,
    ], $attributes));
}

/**
 * Atur stok fisik produk×gudang menjadi $qty pada SATU baris (rak null) dan `qty_reserved` menjadi $reserved.
 * Baris ekstra (mis. dari auto-stok produk) dihapus agar tidak ada baris ganda.
 */
function stkSetStock(Product $product, Warehouse $warehouse, float $qty, float $reserved = 0.0): InventoryStock
{
    $rows = InventoryStock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->orderBy('id')->get();
    $row = $rows->first();

    foreach ($rows->skip(1) as $extra) {
        $extra->forceDelete();
    }

    if ($row === null) {
        return InventoryStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'qty_available' => $qty, 'qty_reserved' => $reserved, 'qty_min' => 0]);
    }

    $row->forceFill(['qty_available' => $qty, 'qty_reserved' => $reserved, 'rak_id' => null])->save();

    return $row->fresh();
}

/** Angka stok produk×gudang: ['available' => fisik, 'reserved' => tertahan, 'free' => bebas]. */
function stkStock(Product $product, Warehouse $warehouse): array
{
    $rows = InventoryStock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->get();
    $available = (float) $rows->sum('qty_available');
    $reserved = (float) $rows->sum('qty_reserved');

    return ['available' => $available, 'reserved' => $reserved, 'free' => $available - $reserved];
}

/** Jumlah baris reservasi (kuantitas) untuk SO / DO. */
function stkReserved(?SaleOrder $saleOrder = null, ?DeliveryOrder $deliveryOrder = null): float
{
    return (float) StockReservation::query()
        ->when($saleOrder, fn ($q) => $q->where('sale_order_id', $saleOrder->id))
        ->when($deliveryOrder, fn ($q) => $q->where('delivery_order_id', $deliveryOrder->id))
        ->sum('quantity');
}

/** SO satu item ($qty) — status default `approved` (dibuat langsung, tanpa memicu jalur approve). */
function stkSaleOrder(array $ctx, float $qty, array $so = [], array $item = []): array
{
    $saleOrder = SaleOrder::create(array_merge([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-STK-'.strtoupper(substr(uniqid(), -6)),
        'order_date' => now(), 'status' => 'approved', 'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1.0,
        'tempo_pembayaran' => 30, 'shipped_to' => 'Jl. Uji Stok No. 1', 'created_by' => $ctx['user']->id,
    ], $so));

    $saleOrderItem = SaleOrderItem::create(array_merge([
        'sale_order_id' => $saleOrder->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty, 'delivered_quantity' => 0, 'unit_price' => 10000,
        'discount' => 0, 'tax' => 0, 'tipe_pajak' => 'none', 'currency_id' => $ctx['idr']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ], $item));

    return [$saleOrder->fresh(), $saleOrderItem];
}

/**
 * DO dengan satu item $qty dari gudang sumber. Dibuat berstatus `request_stock`; $status lain dicapai lewat update() agar observer berjalan
 * (mis. 'approved' → memicu reservasi DO). Jadwal & Surat Jalan TIDAK dibuat (gunakan stkSchedule bila perlu).
 */
function stkDeliveryOrder(array $ctx, SaleOrder $saleOrder, SaleOrderItem $item, float $qty, string $status = 'request_stock', ?Warehouse $source = null): DeliveryOrder
{
    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-STK-'.strtoupper(substr(uniqid(), -6)), 'delivery_date' => now(), 'status' => 'request_stock',
        'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => ($source ?? $ctx['warehouse'])->id,
    ]);
    $deliveryOrder->salesOrders()->attach($saleOrder->id);

    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id, 'sale_order_item_id' => $item->id, 'product_id' => $item->product_id,
        'quantity' => $qty, 'status' => 'requested', 'reason' => 'Uji stok',
    ]);
    DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => ($source ?? $ctx['warehouse'])->id, 'quantity' => $qty]);

    if ($status !== 'request_stock') {
        $deliveryOrder->update(['status' => $status]);
    }

    return $deliveryOrder->fresh();
}

/** Surat Jalan (terbit) + jadwal ekspedisi yang menaut DO; jadwal berstatus $status (default 'pending'). */
function stkSchedule(array $ctx, DeliveryOrder $deliveryOrder, string $status = 'pending'): \App\Models\DeliverySchedule
{
    $suratJalan = \App\Models\SuratJalan::create([
        'sj_number' => 'SJ-STK-'.strtoupper(substr(uniqid(), -6)), 'issued_at' => now(), 'status' => \App\Models\SuratJalan::STATUS_ISSUED,
        'created_by' => $ctx['user']->id, 'cabang_id' => $ctx['cabang']->id,
    ]);
    $suratJalan->deliveryOrder()->sync([$deliveryOrder->id]);

    $schedule = \App\Models\DeliverySchedule::create([
        'schedule_number' => 'SCH-STK-'.strtoupper(substr(uniqid(), -6)), 'scheduled_date' => now(), 'delivery_method' => 'ekspedisi', 'driver_name' => 'JNE Uji',
        'status' => 'pending', 'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id,
    ]);
    $schedule->suratJalan()->attach($suratJalan->id);

    if ($status !== 'pending') {
        $schedule->update(['status' => $status]);
    }

    return $schedule->fresh();
}
