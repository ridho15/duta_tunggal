<?php

/**
 * UAT 13 Nov 2026: Retur by DO dari checker harus bisa disetujui / ditolak kepala gudang di menu Gudang → Persetujuan Retur DO
 * (layar lama ada di panel admin yang sudah dihapus, sehingga retur tertahan di draft dan DO terkunci).
 */

use App\Models\DeliveryOrder;
use App\Models\ReturnProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function doReturnFixture(): array
{
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $item] = stkSaleOrder($ctx, 5);
    $do = stkDeliveryOrder($ctx, $so, $item, 5, 'approved');
    $doItem = $do->deliveryOrderItem()->first();

    return [$ctx, $do, $doItem];
}

function submitDoReturn($test, array $ctx, DeliveryOrder $do, $doItem, int $short = 1, int $reject = 2)
{
    return $test->actingAs($ctx['user'])->post(route('gudang.kiriman.submitRetur', $do->id), [
        'items' => [$doItem->id => ['kurang' => $short, 'reject' => $reject]],
    ]);
}

it('menampilkan retur draft di Persetujuan Retur DO dan menyetujuinya', function () {
    [$ctx, $do, $doItem] = doReturnFixture();
    submitDoReturn($this, $ctx, $do, $doItem)->assertSessionHas('success');
    $retur = ReturnProduct::where('from_model_id', $do->id)->sole();

    $this->get(route('gudang.retur-do.index'))->assertOk()->assertSee($retur->return_number)->assertSee('Setujui');

    $this->post(route('gudang.retur-do.approve', $retur))
        ->assertRedirect(route('gudang.retur-do.index'))->assertSessionHas('success');

    expect($retur->fresh()->status)->toBe('approved')
        ->and($do->fresh()->returned_at)->not->toBeNull()
        ->and(DeliveryOrder::withoutGlobalScopes()->where('replacement_for_delivery_order_id', $do->id)->exists())->toBeTrue();

    // Tidak bisa disetujui dua kali
    $this->post(route('gudang.retur-do.approve', $retur))->assertSessionHas('error');
});

it('menolak retur dengan alasan; DO bisa diretur ulang', function () {
    [$ctx, $do, $doItem] = doReturnFixture();
    submitDoReturn($this, $ctx, $do, $doItem);
    $retur = ReturnProduct::where('from_model_id', $do->id)->sole();

    // Retur kedua diblokir selama yang pertama menunggu, dengan petunjuk menu
    submitDoReturn($this, $ctx, $do, $doItem)->assertSessionHasErrors('lines');

    $this->post(route('gudang.retur-do.reject', $retur), [])->assertSessionHasErrors('reason');
    $this->post(route('gudang.retur-do.reject', $retur), ['reason' => 'Salah hitung, cek ulang'])->assertSessionHas('success');
    expect($retur->fresh()->status)->toBe('rejected')->and($retur->fresh()->reason)->toContain('Salah hitung');

    submitDoReturn($this, $ctx, $do, $doItem)->assertSessionHas('success');
    expect(ReturnProduct::where('from_model_id', $do->id)->where('status', 'draft')->value('return_number'))->toEndWith('R2');
});

it('user tanpa hak persetujuan tidak bisa menyetujui', function () {
    [$ctx, $do, $doItem] = doReturnFixture();
    submitDoReturn($this, $ctx, $do, $doItem);
    $retur = ReturnProduct::where('from_model_id', $do->id)->sole();

    $staff = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $this->actingAs($staff)->post(route('gudang.retur-do.approve', $retur))->assertForbidden();
    expect($retur->fresh()->status)->toBe('draft');
});
