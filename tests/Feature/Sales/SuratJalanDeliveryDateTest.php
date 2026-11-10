<?php

/**
 * Keputusan Ko Handy 10 Nov 2026: stok terpotong pada TANGGAL KIRIM Surat Jalan (jadwal pengiriman; kosong = tanggal SJ dibuat).
 * Contoh Ko Handy: SJ 9 Nov, jadwal kirim 10 Nov → 9 Nov stok belum terpotong, 10 Nov baru terpotong.
 */

use App\Models\DeliveryOrderItem;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo(now()->startOfMonth()->addDays(8)->setTime(10, 0)));   // "9" bulan berjalan

function sjReady(): array
{
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    return [$ctx, $so, $do];
}

function sjPost($test, array $ctx, $do, array $extra = [])
{
    return $test->actingAs($ctx['user'])->post(route('penjualan.sj.store'), array_merge([
        'delivery_order_ids' => [$do->id],
        'issued_at' => now()->toDateString(),
        'delivery_method' => 'ekspedisi',
        'expedition_name' => 'JNE Uji',
        'cabang_id' => $ctx['cabang']->id,
    ], $extra));
}

function sjSalesMovements($do)
{
    return StockMovement::where('type', 'sales')->where('from_model_type', DeliveryOrderItem::class)
        ->whereIn('from_model_id', $do->deliveryOrderItem()->pluck('id'))->get();
}

it('tanggal kirim = hari ini (atau kosong): DO langsung berangkat, stok terpotong bertanggal tanggal kirim', function () {
    [$ctx, , $do] = sjReady();

    sjPost($this, $ctx, $do)->assertSessionHasNoErrors();   // tanpa delivery_date = tanggal SJ

    $sj = SuratJalan::latest('id')->first();
    expect($sj->effectiveDeliveryDate()->toDateString())->toBe(now()->toDateString())
        ->and($do->fresh()->status)->toBe('received')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0])
        ->and(substr((string) sjSalesMovements($do)->first()->date, 0, 10))->toBe(now()->toDateString())
        ->and($sj->deliverySchedules()->first()->status)->toBe('delivered');
});

it('tanggal kirim besok: hari ini stok BELUM terpotong (masih terkunci); besok perintah harian memotongnya bertanggal besok', function () {
    [$ctx, , $do] = sjReady();
    $tomorrow = now()->addDay()->toDateString();

    sjPost($this, $ctx, $do, ['delivery_date' => $tomorrow])->assertSessionHasNoErrors();
    $sj = SuratJalan::latest('id')->first();

    // Hari ini: SJ terbit, DO tetap Siap Kirim, stok fisik utuh dan 12 terkunci, belum ada gerakan keluar
    expect($sj->status)->toBe(SuratJalan::STATUS_ISSUED)
        ->and($do->fresh()->status)->toBe('approved')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 12.0, 'free' => 18.0])
        ->and(sjSalesMovements($do))->toHaveCount(0)
        ->and($sj->deliverySchedules()->first()->status)->toBe('pending');

    // Perintah harian yang jalan hari ini tidak memberangkatkan apa pun
    Artisan::call('sales:dispatch-scheduled-surat-jalan');
    expect($do->fresh()->status)->toBe('approved')->and(sjSalesMovements($do))->toHaveCount(0);

    // Besok: perintah harian memberangkatkan; stok terpotong bertanggal tanggal kirim
    $this->travelTo(now()->addDay()->setTime(0, 15));
    expect(Artisan::call('sales:dispatch-scheduled-surat-jalan'))->toBe(0);

    expect($do->fresh()->status)->toBe('received')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0])
        ->and(sjSalesMovements($do))->toHaveCount(1)
        ->and(substr((string) sjSalesMovements($do)->first()->date, 0, 10))->toBe($tomorrow)
        ->and($sj->deliverySchedules()->first()->status)->toBe('delivered');

    // Idempoten
    Artisan::call('sales:dispatch-scheduled-surat-jalan');
    expect(sjSalesMovements($do))->toHaveCount(1);
});

it('SJ yang tanggal kirimnya belum tiba tidak muncul di Sales Invoicing dan tidak bisa difaktur', function () {
    [$ctx, , $do] = sjReady();
    sjPost($this, $ctx, $do, ['delivery_date' => now()->addDays(2)->toDateString()])->assertSessionHasNoErrors();
    $sj = SuratJalan::latest('id')->first();

    $listed = collect($this->get(route('penjualan.invoices.create'))->assertOk()->viewData('groupedSJs'))
        ->flatMap(fn ($g) => collect($g['surat_jalans'])->pluck('id'));
    expect($listed)->not->toContain($sj->id);

    $this->post(route('penjualan.invoices.store'), [
        'surat_jalan_ids' => [$sj->id], 'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(), 'cabang_id' => $ctx['cabang']->id,
    ])->assertSessionHasErrors('surat_jalan_ids');
});

it('tanggal kirim tidak boleh sebelum tanggal Surat Jalan', function () {
    [$ctx, , $do] = sjReady();

    sjPost($this, $ctx, $do, ['delivery_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('delivery_date');
    expect(SuratJalan::count())->toBe(0)->and($do->fresh()->status)->toBe('approved');
});
