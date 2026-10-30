<?php

/**
 * Tahap 3 Spesifikasi UI/UX (6.5) — checker mengirim DO dari HP tanpa desktop.
 */

use App\Filament\Pages\Hp\KirimanDetailPage;
use App\Filament\Pages\Hp\KirimanPage;
use App\Models\DeliveryOrderItemCheck;
use App\Services\Hp\CheckerQueue;
use App\Support\MenuAccess;
use Livewire\Livewire;

beforeEach(function () {
    config(['sales.stock' => ['require_surat_jalan' => false, 'ledger' => false, 'strict_dispatch' => true, 'reserve_on_so_approve' => false, 'block_short_approval' => false]]);

    $this->ctx = stkContext();
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 50);
    [$so, $soItem] = stkSaleOrder($this->ctx, 5);
    $this->do = stkDeliveryOrder($this->ctx, $so, $soItem, 5, 'approved');

    $this->checker = \App\Models\User::factory()->create([
        'cabang_id' => $this->ctx['cabang']->id,
        'warehouse_id' => $this->ctx['warehouse']->id,
    ]);
    $this->checker->assignRole('Checker');
    MenuAccess::flush();
});

it('daftar kiriman HP hanya memuat DO gudang checker', function () {
    $otherWarehouse = stkWarehouse($this->ctx);
    [$so2, $item2] = stkSaleOrder($this->ctx, 2);
    $otherDo = stkDeliveryOrder($this->ctx, $so2, $item2, 2, 'approved', $otherWarehouse);

    $this->actingAs($this->checker)
        ->get(KirimanPage::getUrl())
        ->assertOk()
        ->assertSee($this->do->do_number)
        ->assertDontSee($otherDo->do_number);
});

it('KIRIM terkunci sampai semua barang dicentang, lalu DO terkirim', function () {
    $this->actingAs($this->checker);
    $item = $this->do->deliveryOrderItem()->first();

    $component = Livewire::test(KirimanDetailPage::class, ['record' => $this->do->id])
        ->assertSee('Semua sesuai – KIRIM')
        ->assertSee('Centang semua barang dulu');

    // Mencoba kirim sebelum dicentang: ditolak, status tetap
    $component->call('ship');
    expect($this->do->fresh()->status)->toBe('approved');

    $component->call('toggle', $item->id);
    expect(DeliveryOrderItemCheck::where('delivery_order_item_id', $item->id)->value('checked_by'))->toBe($this->checker->id);

    $component->call('ship')->assertRedirect(KirimanPage::getUrl());
    expect($this->do->fresh()->status)->toBe('sent');
});

it('scan SKU mencentang barang; SKU asing hanya memberi peringatan', function () {
    $this->actingAs($this->checker);
    $item = $this->do->deliveryOrderItem()->first();

    Livewire::test(KirimanDetailPage::class, ['record' => $this->do->id])
        ->dispatch('barcode-scanned', code: 'TIDAK-ADA-999');
    expect(DeliveryOrderItemCheck::count())->toBe(0);

    Livewire::test(KirimanDetailPage::class, ['record' => $this->do->id])
        ->dispatch('barcode-scanned', code: $this->ctx['product']->sku);
    expect(DeliveryOrderItemCheck::where('delivery_order_item_id', $item->id)->exists())->toBeTrue();
});

it('bila Surat Jalan wajib, KIRIM menunggu SJ dan DO pindah ke tab Menunggu SJ', function () {
    config(['sales.stock.require_surat_jalan' => true]);
    $this->actingAs($this->checker);

    $queue = app(CheckerQueue::class);
    $item = $this->do->deliveryOrderItem()->first();
    $queue->setChecked($item, true, $this->checker);

    expect($queue->blockReason($this->do->fresh(), $queue->checkedItemIds($this->do)))->toContain('Surat Jalan')
        ->and($queue->counts($this->ctx['warehouse']->id))->toMatchArray([CheckerQueue::TAB_READY => 0, CheckerQueue::TAB_WAITING_SJ => 1]);
});

it('checker gudang lain dan peran non-gudang tidak bisa membuka DO', function () {
    $stranger = \App\Models\User::factory()->create(['cabang_id' => $this->ctx['cabang']->id, 'warehouse_id' => stkWarehouse($this->ctx)->id]);
    $stranger->assignRole('Checker');
    $this->actingAs($stranger)->get(KirimanDetailPage::getUrl(['record' => $this->do->id]))->assertForbidden();

    $kasir = \App\Models\User::factory()->create(['cabang_id' => $this->ctx['cabang']->id]);
    $kasir->assignRole('Kasir');
    MenuAccess::flush();
    $this->actingAs($kasir)->get(KirimanPage::getUrl())->assertForbidden();
});

it('navigasi bawah HP tampil dengan menu Kirim untuk checker', function () {
    $this->actingAs($this->checker)
        ->get('/admin')
        ->assertOk()
        ->assertSee('dt-mobile-nav-bar', false)
        ->assertSee(KirimanPage::getUrl(), false);
});

it('tombol [+] Buat baru hanya berisi dokumen yang boleh dibuat peran tersebut', function () {
    $sales = \App\Models\User::factory()->create(['cabang_id' => $this->ctx['cabang']->id]);
    $sales->assignRole('Sales');
    MenuAccess::flush();
    $this->actingAs($sales);

    $labels = array_column(\App\Support\MobileNav::createOptions(), 'label');

    expect($labels)->toContain('Pesanan Penjualan (SO)')
        ->not->toContain('Pindah Stok')
        ->not->toContain('Terima Pembayaran');
});
