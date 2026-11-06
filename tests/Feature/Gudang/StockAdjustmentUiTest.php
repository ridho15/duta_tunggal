<?php

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\MenuAccess;
use Spatie\Permission\Models\Role;

function adjUser(string $role = 'Admin Inventory'): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    MenuAccess::flush();
    $user = User::factory()->create(['manage_type' => ['all']]);
    $user->assignRole($role);

    return $user;
}

/** Gudang + produk dengan stok fisik $qty. */
function adjSetup(float $qty = 10): array
{
    $warehouse = Warehouse::factory()->create(['status' => 1]);
    $product = Product::factory()->create(['cost_price' => 1000]);
    // Baris stok (produk, gudang, tanpa rak) bisa sudah ada dari pembuatan produk: ubah, jangan gandakan.
    $stock = InventoryStock::updateOrCreate(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'rak_id' => null], ['qty_available' => $qty, 'qty_reserved' => 0, 'qty_min' => 0]);
    // Persetujuan membuat jurnal: butuh akun persediaan dan akun selisih (kode default service).
    foreach ([['1140.10', 'Asset'], ['7000.04', 'Revenue'], ['8000.05', 'Expense']] as [$code, $type]) {
        ChartOfAccount::firstOrCreate(['code' => $code], ['name' => 'Akun uji '.$code, 'type' => $type]);
    }

    return [$warehouse, $product, $stock];
}

function adjPayload($warehouse, $product, array $override = []): array
{
    return array_merge([
        'adjustment_date' => '2026-11-05', 'warehouse_id' => $warehouse->id, 'adjustment_type' => 'increase', 'reason' => 'Hitung fisik lebih banyak',
        'items' => [['product_id' => $product->id, 'rak_id' => '', 'adjusted_qty' => 15, 'unit_cost' => '1.000', 'notes' => '']],
    ], $override);
}

it('Kasir ditolak di semua halaman dan endpoint penyesuaian stok', function () {
    [$warehouse, $product] = adjSetup();
    $kasir = adjUser('Kasir');

    $this->actingAs($kasir)->get(route('gudang.adjustment.index'))->assertForbidden();
    $this->actingAs($kasir)->get(route('gudang.adjustment.create'))->assertForbidden();
    $this->actingAs($kasir)->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product))->assertForbidden();
    $this->actingAs($kasir)->getJson(route('gudang.adjustment.products', ['q' => 'a']))->assertForbidden();
    expect(StockAdjustment::count())->toBe(0);
});

it('menyimpan draft dengan qty saat ini, selisih, dan nilai dihitung server; stok belum berubah', function () {
    [$warehouse, $product, $stock] = adjSetup(10);

    $this->actingAs(adjUser())->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product))->assertRedirect();

    $adjustment = StockAdjustment::firstOrFail();
    $item = $adjustment->items()->firstOrFail();
    expect($adjustment->status)->toBe('draft')
        ->and((float) $item->current_qty)->toBe(10.0)
        ->and((float) $item->difference_qty)->toBe(5.0)
        ->and((float) $item->unit_cost)->toBe(1000.0)
        ->and((float) $item->difference_value)->toBe(5000.0)
        ->and((float) $stock->fresh()->qty_available)->toBe(10.0);
});

it('menolak selisih yang berlawanan dengan tipe, tanpa menyisakan data', function () {
    [$warehouse, $product] = adjSetup(10);
    $user = adjUser();

    $this->actingAs($user)->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product, ['adjustment_type' => 'increase', 'items' => [['product_id' => $product->id, 'adjusted_qty' => 4, 'unit_cost' => 1000]]]))
        ->assertSessionHasErrors('items.0.adjusted_qty');
    $this->actingAs($user)->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product, ['adjustment_type' => 'decrease', 'items' => [['product_id' => $product->id, 'adjusted_qty' => 10, 'unit_cost' => 1000]]]))
        ->assertSessionHasErrors('items.0.adjusted_qty');   // selisih nol

    expect(StockAdjustment::count())->toBe(0);
});

it('rak harus berasal dari gudang yang dipilih', function () {
    [$warehouse, $product] = adjSetup();
    $otherRak = Rak::factory()->create(['warehouse_id' => Warehouse::factory()->create(['status' => 1])->id]);

    $this->actingAs(adjUser())->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product, ['items' => [['product_id' => $product->id, 'rak_id' => $otherRak->id, 'adjusted_qty' => 15, 'unit_cost' => 1000]]]))
        ->assertSessionHasErrors('items.0.rak_id');
    expect(StockAdjustment::count())->toBe(0);
});

it('menyetujui penambahan: stok naik dan mutasi tercatat; tidak bisa diubah atau dihapus lagi', function () {
    [$warehouse, $product, $stock] = adjSetup(10);
    $user = adjUser();
    $this->actingAs($user)->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product));
    $adjustment = StockAdjustment::firstOrFail();

    $this->actingAs($user)->post(route('gudang.adjustment.approve', $adjustment))->assertSessionHas('success');

    expect($adjustment->fresh()->status)->toBe('approved')
        ->and((float) $stock->fresh()->qty_available)->toBe(15.0)
        ->and(StockMovement::withoutGlobalScopes()->where('from_model_type', StockAdjustment::class)->where('from_model_id', $adjustment->id)->where('type', 'adjustment_in')->exists())->toBeTrue();

    $this->actingAs($user)->get(route('gudang.adjustment.edit', $adjustment))->assertRedirect(route('gudang.adjustment.show', $adjustment));
    $this->actingAs($user)->delete(route('gudang.adjustment.destroy', $adjustment))->assertSessionHas('error');
    expect(StockAdjustment::count())->toBe(1);
});

it('pengurangan melebihi stok bebas gagal disetujui dan tetap draft', function () {
    [$warehouse, $product, $stock] = adjSetup(10);
    $user = adjUser();
    $this->actingAs($user)->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product, ['adjustment_type' => 'decrease', 'items' => [['product_id' => $product->id, 'adjusted_qty' => 4, 'unit_cost' => 1000]]]));
    $adjustment = StockAdjustment::firstOrFail();
    $stock->update(['qty_reserved' => 8]);   // stok bebas tinggal 2, pengurangan 6

    $this->actingAs($user)->post(route('gudang.adjustment.approve', $adjustment))->assertSessionHasErrors('stock');

    expect($adjustment->fresh()->status)->toBe('draft')->and((float) $stock->fresh()->qty_available)->toBe(10.0);
});

it('draft dapat diubah, ditolak (stok tetap), lalu dihapus', function () {
    [$warehouse, $product, $stock] = adjSetup(10);
    $user = adjUser();
    $this->actingAs($user)->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product));
    $adjustment = StockAdjustment::firstOrFail();

    $this->actingAs($user)->put(route('gudang.adjustment.update', $adjustment), adjPayload($warehouse, $product, ['reason' => 'Diubah', 'items' => [['product_id' => $product->id, 'adjusted_qty' => 20, 'unit_cost' => 2000]]]))
        ->assertRedirect(route('gudang.adjustment.show', $adjustment));
    $item = $adjustment->fresh()->items()->firstOrFail();
    expect($adjustment->fresh()->reason)->toBe('Diubah')->and($adjustment->items()->count())->toBe(1)
        ->and((float) $item->difference_value)->toBe(20000.0);

    $this->actingAs($user)->post(route('gudang.adjustment.reject', $adjustment))->assertSessionHas('success');
    expect($adjustment->fresh()->status)->toBe('rejected')->and((float) $stock->fresh()->qty_available)->toBe(10.0);

    $this->actingAs($user)->delete(route('gudang.adjustment.destroy', $adjustment))->assertRedirect(route('gudang.adjustment.index'));
    expect(StockAdjustment::count())->toBe(0);
});

it('halaman daftar, detail, dan form tampil; endpoint pencarian, rak, dan stok menjawab', function () {
    [$warehouse, $product] = adjSetup(10);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $user = adjUser();
    $this->actingAs($user)->post(route('gudang.adjustment.store'), adjPayload($warehouse, $product));
    $adjustment = StockAdjustment::firstOrFail();

    $this->actingAs($user)->get(route('gudang.adjustment.index'))->assertOk()->assertSee($adjustment->adjustment_number);
    $this->actingAs($user)->get(route('gudang.adjustment.show', $adjustment))->assertOk()->assertSee('Setujui')->assertSee($product->name);
    $this->actingAs($user)->get(route('gudang.adjustment.create'))->assertOk()->assertSee('Item penyesuaian');
    $this->actingAs($user)->get(route('gudang.adjustment.edit', $adjustment))->assertOk()->assertSee($product->name);
    $this->actingAs($user)->getJson(route('gudang.adjustment.products', ['q' => $product->name]))->assertOk()->assertJsonFragment(['id' => $product->id]);
    $this->actingAs($user)->getJson(route('gudang.adjustment.raks', ['warehouse_id' => $warehouse->id]))->assertOk()->assertJsonFragment(['id' => $rak->id]);
    $this->actingAs($user)->getJson(route('gudang.adjustment.stock', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id]))->assertOk()->assertJson(['qty' => 10]);
});
