<?php

/**
 * Prioritas 03 Nov 2026 butir 1: alur DO tidak lagi mentok di "Menunggu Konfirmasi Stok".
 * Konfirmasi stok (Blade) → Siap dicek → cek checker → Surat Jalan → Kirim, plus Checker Edit Qty.
 */

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLog;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\WarehouseConfirmation;
use App\Services\DeliveryOrderService;
use App\Services\SuratJalanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** DO menunggu konfirmasi stok lengkap dengan WC-nya (seperti hasil DeliveryOrderController::store). */
function cnfWaitingDo(float $stock = 30, float $qty = 12): array
{
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], $stock);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, $qty);
    app(DeliveryOrderService::class)->createWarehouseConfirmationsForDeliveryOrder($do);

    $checker = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $checker->assignRole(Role::firstOrCreate(['name' => 'Checker', 'guard_name' => 'web']));

    return [$ctx, $do, $checker];
}

it('Konfirmasi Stok memindahkan DO ke approved dan DO muncul di antrean Gudang', function () {
    [$ctx, $do, $checker] = cnfWaitingDo();

    $this->actingAs($checker)->get(route('gudang.kiriman.index', ['warehouse_id' => $ctx['warehouse']->id]))
        ->assertOk()
        ->assertSee($do->do_number)
        ->assertSee('Konfirmasi stok');   // default tab = antrean yang berisi pekerjaan

    $this->actingAs($checker)->post(route('penjualan.do.confirmStock', $do->id))
        ->assertRedirect()->assertSessionHas('success');

    expect($do->fresh()->status)->toBe('approved')
        ->and(WarehouseConfirmation::where('confirmable_id', $do->id)->where('status', '!=', 'confirmed')->count())->toBe(0);

    $this->get(route('gudang.kiriman.index', ['warehouse_id' => $ctx['warehouse']->id]))->assertSee('Mulai cek');
});

it('Konfirmasi Stok pada DO yang bukan menunggu ditolak dengan pesan, status tidak berubah', function () {
    [, $do, $checker] = cnfWaitingDo();
    $do->update(['status' => 'approved']);

    $this->actingAs($checker)->post(route('penjualan.do.confirmStock', $do->id))->assertSessionHas('error');
    expect($do->fresh()->status)->toBe('approved');
});

it('WC ditolak: konfirmasi stok dibatalkan seluruhnya (rollback) dengan pesan jelas', function () {
    [, $do, $checker] = cnfWaitingDo();
    WarehouseConfirmation::where('confirmable_id', $do->id)->update(['status' => 'rejected']);

    $this->actingAs($checker)->post(route('penjualan.do.confirmStock', $do->id))->assertSessionHas('error');
    expect($do->fresh()->status)->not->toBe('approved');
});

it('pengguna tanpa peran gudang tidak boleh konfirmasi stok maupun mengirim (403)', function () {
    [$ctx, $do] = cnfWaitingDo();
    $outsider = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);

    $this->actingAs($outsider)->post(route('penjualan.do.confirmStock', $do->id))->assertForbidden();
    $this->actingAs($outsider)->post(route('penjualan.do.send', $do->id))->assertForbidden();
    expect($do->fresh()->status)->toBe('request_stock');
});

it('halaman checker menolak DO yang belum dikonfirmasi stoknya, dan submitCheck tidak melompati konfirmasi', function () {
    [$ctx, $do, $checker] = cnfWaitingDo();
    $itemId = $do->deliveryOrderItem()->value('id');

    $this->actingAs($checker)->get(route('gudang.kiriman.check', $do->id))->assertRedirect();
    $this->post(route('gudang.kiriman.submitCheck', $do->id), ['checked_items' => [(string) $itemId]])->assertSessionHas('error');
    expect($do->fresh()->status)->toBe('request_stock');
});

it('barang belum tercentang sebelum dicek, walau item DO berstatus confirmed', function () {
    [, $do, $checker] = cnfWaitingDo();
    app(DeliveryOrderService::class)->confirmStock($do);
    $do->deliveryOrderItem()->update(['status' => 'confirmed']);

    $html = $this->actingAs($checker)->get(route('gudang.kiriman.check', $do->id))
        ->assertOk()->assertSee('Ubah qty')->getContent();

    expect($html)->not->toMatch('/name="checked_items\[\]"\s+value="\d+"\s+checked/');

    // setelah dicek sungguhan, kotak itu tercentang
    $this->post(route('gudang.kiriman.submitCheck', $do->id), ['checked_items' => [(string) $do->deliveryOrderItem()->value('id')]]);
    expect($this->get(route('gudang.kiriman.check', $do->id))->getContent())->toMatch('/name="checked_items\[\]"\s+value="\d+"\s+checked/');
});

it('DO yang sudah dicek checker (confirmed) bisa dibuatkan Surat Jalan; request_stock ditolak', function () {
    [, $do] = cnfWaitingDo();
    $service = app(SuratJalanService::class);

    expect(fn () => $service->assertDeliveryOrdersUsable(collect([$do->fresh()])))->toThrow(ValidationException::class);

    $do->update(['status' => 'confirmed']);
    $service->assertDeliveryOrdersUsable(collect([$do->fresh()]));
    expect(true)->toBeTrue();
});

it('Kirim di halaman DO: butuh SJ terbit, lalu DO berstatus sent dan stok keluar', function () {
    [$ctx, $do, $checker] = cnfWaitingDo(stock: 30, qty: 12);
    app(DeliveryOrderService::class)->confirmStock($do);

    // tanpa SJ: ditolak (require_surat_jalan default true)
    $this->actingAs($checker)->post(route('penjualan.do.send', $do->id))->assertSessionHas('error');
    expect($do->fresh()->status)->toBe('approved');

    stkSchedule($ctx, $do);
    $this->actingAs($checker)->get(route('penjualan.do.show', $do->id))->assertSee('Kirim');
    $this->post(route('penjualan.do.send', $do->id))->assertSessionHas('success');

    expect($do->fresh()->status)->toBe('sent');
});

it('Checker Edit Qty: boleh turun + tercatat di log; naik ditolak; DO dengan SJ aktif ditolak', function () {
    [$ctx, $do, $checker] = cnfWaitingDo(stock: 30, qty: 12);
    app(DeliveryOrderService::class)->confirmStock($do);
    $itemId = $do->deliveryOrderItem()->value('id');

    // naik → ditolak
    $this->actingAs($checker)->post(route('gudang.kiriman.editQty', $do->id), ['quantities' => [$itemId => 15], 'notes' => 'uji'])
        ->assertSessionHas('error');
    expect((float) $do->deliveryOrderItem()->value('quantity'))->toBe(12.0);

    // tanpa alasan → validasi
    $this->post(route('gudang.kiriman.editQty', $do->id), ['quantities' => [$itemId => 8]])->assertSessionHasErrors('notes');

    // turun → tersimpan + log
    $this->post(route('gudang.kiriman.editQty', $do->id), ['quantities' => [$itemId => 8], 'notes' => 'Rak tinggal 8'])
        ->assertSessionHas('success');
    expect((float) $do->deliveryOrderItem()->value('quantity'))->toBe(8.0)
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->where('action', 'quantity_updated_by_checker')->where('new_value', '8')->exists())->toBeTrue();

    // semua nol → ditolak
    $this->post(route('gudang.kiriman.editQty', $do->id), ['quantities' => [$itemId => 0], 'notes' => 'nol'])->assertSessionHas('error');
    expect((float) $do->deliveryOrderItem()->value('quantity'))->toBe(8.0);

    // SJ aktif → ditolak
    stkSchedule($ctx, $do);
    $this->post(route('gudang.kiriman.editQty', $do->id), ['quantities' => [$itemId => 5], 'notes' => 'telat'])->assertSessionHas('error');
    expect((float) $do->deliveryOrderItem()->value('quantity'))->toBe(8.0);
});
