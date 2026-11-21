<?php

/**
 * Keputusan Ko Handy 21 Nov 2026 (merevisi 10 Nov): stok terpotong saat SJ TERBENTUK, walau tanggal kirimnya besok.
 * Tanggal kirim tetap dipakai untuk jadwal pengiriman (logistik); SJ yang sudah terbentuk boleh langsung difaktur.
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

it('tanggal kirim besok: stok TETAP terpotong hari ini saat SJ terbentuk; jadwal kirim tetap menunggu tanggalnya', function () {
    [$ctx, , $do] = sjReady();
    $tomorrow = now()->addDay()->toDateString();

    sjPost($this, $ctx, $do, ['delivery_date' => $tomorrow])->assertSessionHasNoErrors();
    $sj = SuratJalan::latest('id')->first();

    expect($sj->status)->toBe(SuratJalan::STATUS_ISSUED)
        ->and($do->fresh()->status)->toBe('received')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0])
        ->and(sjSalesMovements($do))->toHaveCount(1)
        ->and(substr((string) sjSalesMovements($do)->first()->date, 0, 10))->toBe(now()->toDateString())   // tanggal SJ dibuat
        ->and($sj->deliverySchedules()->first()->status)->toBe('pending');                               // logistik menunggu besok

    // Perintah harian tidak memotong lagi (idempoten) dan jadwal baru ditandai terkirim saat tanggal kirim tiba
    Artisan::call('sales:dispatch-scheduled-surat-jalan');
    expect(sjSalesMovements($do))->toHaveCount(1)->and($sj->deliverySchedules()->first()->status)->toBe('pending');
});

it('SJ yang sudah terbentuk langsung muncul di Sales Invoicing dan bisa difaktur walau tanggal kirim belum tiba', function () {
    [$ctx, , $do] = sjReady();
    sjPost($this, $ctx, $do, ['delivery_date' => now()->addDays(2)->toDateString()])->assertSessionHasNoErrors();
    $sj = SuratJalan::latest('id')->first();

    $listed = collect($this->get(route('penjualan.invoices.create'))->assertOk()->viewData('groupedSJs'))
        ->flatMap(fn ($g) => collect($g['surat_jalans'])->pluck('id'));
    expect($listed)->toContain($sj->id);
});

it('perintah harian menyusulkan potong stok untuk SJ lama yang sempat tertunda (dibuat sebelum aturan 21 Nov)', function () {
    [$ctx, , $do] = sjReady();

    // Data lama: SJ terbit bertanggal kirim masa depan, DO masih Siap Kirim (stok baru terkunci, belum terpotong)
    $sj = SuratJalan::create([
        'sj_number' => 'SJ-LAMA-001', 'issued_at' => now(), 'delivery_date' => now()->addDays(3),
        'status' => SuratJalan::STATUS_ISSUED, 'created_by' => $ctx['user']->id, 'cabang_id' => $ctx['cabang']->id,
    ]);
    $sj->deliveryOrder()->attach($do->id);
    expect(sjSalesMovements($do))->toHaveCount(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(30.0);

    expect(Artisan::call('sales:dispatch-scheduled-surat-jalan'))->toBe(0);
    expect($do->fresh()->status)->toBe('received')->and(sjSalesMovements($do))->toHaveCount(1)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);
});

it('tanggal kirim tidak boleh sebelum tanggal Surat Jalan', function () {
    [$ctx, , $do] = sjReady();

    sjPost($this, $ctx, $do, ['delivery_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('delivery_date');
    expect(SuratJalan::count())->toBe(0)->and($do->fresh()->status)->toBe('approved');
});
