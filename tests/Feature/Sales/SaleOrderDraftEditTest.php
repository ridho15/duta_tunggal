<?php

/**
 * Keputusan Ko Handy #3c (15 Nov): SO draft harus bisa diubah (dilanjutkan). Hanya status draft,
 * oleh pembuat/Manager/Super Admin.
 */

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function draftSo(array $ctx, string $status = 'draft'): SaleOrder
{
    $so = SaleOrder::factory()->create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'status' => $status, 'created_by' => $ctx['user']->id, 'currency_id' => $ctx['idr']->id,
        'tipe_pengiriman' => 'Kirim Langsung', 'order_date' => now()->toDateString(), 'total_amount' => 20000,
    ]);
    SaleOrderItem::factory()->create(['sale_order_id' => $so->id, 'product_id' => $ctx['product']->id,
        'quantity' => 2, 'unit_price' => 10000, 'discount' => 0, 'tax' => 0, 'tipe_pajak' => 'none']);

    return $so;
}

function editPayload(array $ctx, float $qty, float $price): array
{
    return [
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'order_date' => now()->toDateString(),
        'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['idr']->id, 'action' => 'draft',
        'items' => [['product_id' => $ctx['product']->id, 'quantity' => $qty, 'unit_price' => $price, 'discount' => 0, 'tax_type' => 'None']],
    ];
}

it('pembuat bisa membuka & menyimpan perubahan SO draft', function () {
    $ctx = stkContext();
    $so = draftSo($ctx);

    $this->actingAs($ctx['user'])->get(route('penjualan.so.edit', $so))->assertOk()->assertSee('Ubah Pesanan Penjualan');

    $this->actingAs($ctx['user'])->put(route('penjualan.so.update', $so), editPayload($ctx, 5, 12000))
        ->assertRedirect(route('penjualan.so.show', $so->id))->assertSessionHasNoErrors();

    $so->refresh();
    expect($so->saleOrderItem()->count())->toBe(1)
        ->and((float) $so->saleOrderItem->first()->quantity)->toBe(5.0)
        ->and((float) $so->saleOrderItem->first()->unit_price)->toBe(12000.0);
});

it('SO yang sudah diajukan/disetujui tidak bisa diubah', function () {
    $ctx = stkContext();
    $so = draftSo($ctx, 'approved');

    $this->actingAs($ctx['user'])->get(route('penjualan.so.edit', $so))->assertRedirect(route('penjualan.so.show', $so->id));
    $this->actingAs($ctx['user'])->put(route('penjualan.so.update', $so), editPayload($ctx, 9, 12000))->assertRedirect();
    expect((float) $so->fresh()->saleOrderItem->first()->quantity)->toBe(2.0); // tak berubah
});

it('user lain (bukan pembuat / bukan manager) tidak bisa mengubah', function () {
    $ctx = stkContext();
    $so = draftSo($ctx);
    Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
    $other = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $other->assignRole('Sales');
    MenuAccess::flush();

    $this->actingAs($other)->put(route('penjualan.so.update', $so), editPayload($ctx, 9, 12000))->assertRedirect(route('penjualan.so.show', $so->id));
    expect((float) $so->fresh()->saleOrderItem->first()->quantity)->toBe(2.0);
});

it('#1 batas harga beli juga berlaku saat edit SO draft', function () {
    $ctx = stkContext();            // modal 5000
    $so = draftSo($ctx);
    // harga 4000 < modal 5000 -> ditolak (lewat ProductPriceLimitation)
    $this->actingAs($ctx['user'])->put(route('penjualan.so.update', $so), editPayload($ctx, 2, 4000))
        ->assertSessionHasErrors('items.0.unit_price');
});
