<?php

/**
 * Pusat Laporan (Ko Handy 28 Nov 2026): Detail Penjualan, Stok Barang Terjual, Cetak Banyak Produk.
 */

use App\Models\SaleOrder;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function rcUser(array $ctx, string $role): User
{
    $u = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => ['all']]);
    $u->assignRole(Role::findByName($role));
    Access::flush();

    return $u;
}

beforeEach(function () {
    $this->ctx = stkContext();
    $this->owner = rcUser($this->ctx, 'Owner');

    // SO 8 pcs, DO 4 pcs terkirim, faktur 8 pcs @10.000 diskon 10%, retur 1 pcs
    [$this->so, $soItem] = stkSaleOrder($this->ctx, 8);
    $do = stkDeliveryOrder($this->ctx, $this->so, $soItem, 4);
    DB::table('delivery_orders')->where('id', $do->id)->update(['status' => 'sent']);

    $invoiceId = DB::table('invoices')->insertGetId([
        'invoice_number' => 'INV-RC-001', 'tax_invoice_number' => '010.000-26.00000001', 'from_model_type' => SaleOrder::class,
        'from_model_id' => $this->so->id, 'invoice_date' => '2026-11-20', 'due_date' => '2026-12-20', 'status' => 'unpaid',
        'cabang_id' => $this->ctx['cabang']->id, 'total' => 72000, 'subtotal' => 72000, 'tipe_pajak' => 'Non Pajak',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->itemId = DB::table('invoice_items')->insertGetId([
        'invoice_id' => $invoiceId, 'product_id' => $this->ctx['product']->id, 'quantity' => 8, 'price' => 10000, 'discount' => 10,
        'subtotal' => 72000, 'total' => 72000, 'tax_rate' => 0, 'tax_amount' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $returnId = DB::table('customer_returns')->insertGetId([
        'return_number' => 'RTR-RC-1', 'invoice_id' => $invoiceId, 'return_date' => '2026-11-22', 'reason' => 'Rusak', 'status' => 'approved',
        'cabang_id' => $this->ctx['cabang']->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('customer_return_items')->insert([
        'customer_return_id' => $returnId, 'product_id' => $this->ctx['product']->id, 'invoice_item_id' => $this->itemId, 'quantity' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

it('Pusat Laporan menampilkan laporan sesuai akses peran', function () {
    $this->actingAs($this->owner)->get(route('laporan.pusat.index'))->assertOk()
        ->assertSee('Detail Penjualan')->assertSee('Stok Barang Terjual')->assertSee('Cetak Banyak Produk')
        ->assertSee('href="' . route('laporan.pusat.index') . '" class="flex items-center', false);   // menu samping

    $this->actingAs(rcUser($this->ctx, 'Sales'))->get(route('laporan.pusat.index'))->assertOk()
        ->assertSee('Detail Penjualan')->assertDontSee('Cetak Banyak Produk');

    $this->actingAs(rcUser($this->ctx, 'Warehouse Staff'))->get(route('laporan.pusat.index'))->assertOk()
        ->assertSee('Stok Barang Terjual')->assertSee('Cetak Banyak Produk')->assertDontSee('Detail Penjualan');
});

it('Detail Penjualan: baris faktur, status kirim sebagian, retur, ringkasan', function () {
    $res = $this->actingAs($this->owner)->get(route('laporan.penjualan-detail.index', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']))->assertOk();
    $res->assertSee('INV-RC-001')->assertSee($this->so->so_number)->assertSee('010.000-26.00000001')
        ->assertSee('Terkirim 4 dari 8')->assertSee('Rp 72.000')->assertSee('Rp 9.000');   // retur 1 x 9.000

    $this->get(route('laporan.penjualan-detail.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk()
        ->assertSee('Tidak ada penjualan pada periode');
    $this->get(route('laporan.penjualan-detail.index', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30', 'q' => 'TIDAK-ADA']))
        ->assertOk()->assertDontSee('INV-RC-001');
    $this->get(route('laporan.penjualan-detail.pdf', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']))->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->get(route('laporan.penjualan-detail.excel', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']))->assertOk();

    $this->actingAs(rcUser($this->ctx, 'Sales'))->get(route('laporan.penjualan-detail.excel'))->assertForbidden();
});

it('Stok Barang Terjual: qty jual per gudang dibanding stok & minimum', function () {
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 3);
    DB::table('inventory_stocks')->where('product_id', $this->ctx['product']->id)->where('warehouse_id', $this->ctx['warehouse']->id)->update(['qty_min' => 5]);
    DB::table('stock_movements')->insert([
        'product_id' => $this->ctx['product']->id, 'warehouse_id' => $this->ctx['warehouse']->id, 'quantity' => 4, 'type' => 'sales',
        'date' => '2026-11-27', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($this->owner)->get(route('laporan.stok-terjual.index', ['date_from' => '2026-11-27']))->assertOk()
        ->assertSee($this->ctx['product']->sku)->assertSee('Di bawah minimum')->assertSee('Perlu restok');
    $this->get(route('laporan.stok-terjual.index', ['date_from' => '2026-11-26']))->assertOk()->assertSee('Tidak ada barang terjual');
    $this->get(route('laporan.stok-terjual.pdf', ['date_from' => '2026-11-27']))->assertOk();
});

it('Cetak Banyak Produk: rentang kode, qty kosong, nilai hanya untuk yang berhak', function () {
    $zero = stkProduct($this->ctx, ['sku' => 'ZZZ-0001', 'name' => 'Produk Kosong', 'cost_price' => 2000]);
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 6);
    stkSetStock($zero, $this->ctx['warehouse'], 0);

    $this->actingAs($this->owner)->get(route('laporan.cetak-produk.index'))->assertOk()
        ->assertSee($this->ctx['product']->sku)->assertDontSee('ZZZ-0001');
    $this->get(route('laporan.cetak-produk.index', ['include_zero' => 1, 'sku_from' => 'ZZZ']))->assertOk()
        ->assertSee('ZZZ-0001')->assertDontSee($this->ctx['product']->sku);
    $this->get(route('laporan.cetak-produk.index', ['with_value' => 1]))->assertOk()->assertSee('Nilai persediaan')->assertSee('Rp 30.000');
    $this->get(route('laporan.cetak-produk.pdf', ['with_value' => 1]))->assertOk();

    // Staf gudang: tidak punya "Lihat Nilai" → nilai tidak tampil walau diminta
    $staff = rcUser($this->ctx, 'Warehouse Staff');
    $this->actingAs($staff)->get(route('laporan.cetak-produk.index', ['with_value' => 1]))->assertOk()->assertDontSee('Nilai persediaan');
    $this->get(route('laporan.cetak-produk.excel'))->assertForbidden();
});
