<?php

/**
 * T2.3 — DeliveryOrderTransitions (flag sales.stock.strict_dispatch): satu pintu status DO, matriks, idempotensi, larangan stok negatif (D15),
 * jadwal wajib Mulai dulu (D5), gagal-kirim mengembalikan stok (D18/D20), Batalkan DO (D19), penerimaan (D23).
 */

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLog;
use App\Models\StockMovement;
use App\Services\DeliveryOrderService;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => ['ledger' => false, 'strict_dispatch' => true, 'reserve_on_so_approve' => false, 'block_short_approval' => false]]);
});

/** DO Siap Kirim 12 dari stok $stock; SO qty 20. */
function trxReady(float $stock = 30, float $qty = 12): array
{
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], $stock);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, $qty, 'approved');

    return [$ctx, $so, $item, $do];
}

function trxSalesMovements(?DeliveryOrder $do = null): int
{
    return StockMovement::where('type', 'sales')->count();
}

// ------------------------------------------------------------------ matriks

it('matriks: transisi sah diterima dan yang di luar matriks ditolak', function (string $from, string $to, bool $allowed) {
    expect(DeliveryOrderTransitions::allows($from, $to))->toBe($allowed);
})->with([
    ['request_stock', 'approved', true],
    ['request_stock', 'reject', true],
    ['approved', 'sent', true],
    ['approved', 'closed', true],
    ['approved', 'delivery_failed', true],
    ['sent', 'received', true],
    ['sent', 'completed', true],
    ['sent', 'delivery_failed', true],
    ['received', 'completed', true],
    ['delivery_failed', 'approved', true],
    ['delivery_failed', 'sent', true],
    ['request_close', 'closed', true],
    // di luar matriks
    ['approved', 'completed', false],
    ['approved', 'received', false],
    ['sent', 'approved', false],
    ['sent', 'closed', false],
    ['received', 'sent', false],
    ['received', 'delivery_failed', false],
    ['completed', 'sent', false],
    ['completed', 'closed', false],
    ['closed', 'approved', false],
    ['draft', 'sent', false],
    ['request_stock', 'sent', false],
    ['request_approve', 'sent', false],
    ['bukan_status', 'sent', false],
]);

it('status final (completed, closed) tidak punya tujuan', function () {
    expect(DeliveryOrderTransitions::targetsFrom('completed'))->toBe([])
        ->and(DeliveryOrderTransitions::targetsFrom('closed'))->toBe([]);
});

it('transisi di luar matriks ditolak dengan pesan berbahasa Indonesia dan status tidak berubah', function () {
    [, , , $do] = trxReady();

    try {
        app(DeliveryOrderTransitions::class)->to($do, 'completed');
        $this->fail('seharusnya ditolak');
    } catch (DeliveryOrderTransitionException $e) {
        expect($e->getMessage())->toContain('tidak dapat diubah dari "Siap Kirim" ke "Selesai"')
            ->and($e->getMessage())->toContain('Langkah yang diizinkan');
    }

    expect($do->fresh()->status)->toBe('approved')
        ->and(trxSalesMovements())->toBe(0);
});

it('label "Siap Kirim" hanya saat alur ketat aktif', function () {
    expect(DeliveryOrder::statusLabel('approved'))->toBe('Siap Kirim')
        ->and(DeliveryOrder::statusLabel('sent'))->toBe('Dikirim');

    config(['sales.stock.strict_dispatch' => false]);

    expect(DeliveryOrder::statusLabel('approved'))->toBe('Disetujui')
        ->and(DeliveryOrder::statusLabel('sent'))->toBe('Sedang Dikirim');
});

// ------------------------------------------------------------------ Kirim: stok, idempotensi, log, item

it('Kirim: stok fisik keluar sekali, reservasi DO dikonsumsi, item DO "sent", log tercatat', function () {
    [$ctx, $so, , $do] = trxReady();
    expect(stkReserved($so, $do))->toBe(12.0);

    app(DeliveryOrderTransitions::class)->to($do, 'sent');

    $do = $do->fresh();
    expect($do->status)->toBe('sent')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0])
        ->and(stkReserved($so, $do))->toBe(0.0)
        ->and($do->deliveryOrderItem->first()->status)->toBe('sent')
        ->and(trxSalesMovements())->toBe(1);

    $log = DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'sent')->first();
    expect($log)->not->toBeNull()
        ->and($log->old_value)->toBe('approved')
        ->and((int) $log->confirmed_by)->toBe((int) $ctx['user']->id);
});

it('Kirim dua kali (klik ganda) = satu efek', function () {
    [$ctx, , , $do] = trxReady();
    $service = app(DeliveryOrderTransitions::class);

    $service->to($do, 'sent');
    $service->to($do->fresh(), 'sent');
    $service->to($do, 'sent');   // instance basi (status di memori masih approved)

    expect(trxSalesMovements())->toBe(1)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0)
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'sent')->count())->toBe(1);
});

// ------------------------------------------------------------------ D15

it('D15: stok fisik kurang menolak Kirim dengan rincian; stok, status dan gerakan tidak berubah', function () {
    [$ctx, , , $do] = trxReady(stock: 8, qty: 12);

    try {
        app(DeliveryOrderTransitions::class)->to($do, 'sent');
        $this->fail('seharusnya ditolak');
    } catch (DeliveryOrderTransitionException $e) {
        expect($e->getMessage())->toContain('Alat Uji Stok: butuh 12')
            ->and($e->getMessage())->toContain('stok fisik Gudang Utama hanya 8')
            ->and($e->shortages)->toHaveCount(1);
    }

    expect($do->fresh()->status)->toBe('approved')
        ->and(trxSalesMovements())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(8.0);
});

it('D15: pengecualian butuh Owner/Super Admin dan alasan; tercatat di log', function () {
    [$ctx, , , $do] = trxReady(stock: 8, qty: 12);
    $service = app(DeliveryOrderTransitions::class);

    // pengguna biasa: ditolak walau meminta pengecualian
    expect(fn () => $service->to($do, 'sent', ['override_negative_stock' => true, 'reason' => 'mendesak']))
        ->toThrow(DeliveryOrderTransitionException::class, 'Hanya Owner atau Super Admin');

    Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Owner');
    $ctx['user']->unsetRelation('roles');
    Auth::login($ctx['user']->fresh());

    // Owner tanpa alasan: ditolak
    expect(fn () => $service->to($do, 'sent', ['override_negative_stock' => true]))
        ->toThrow(DeliveryOrderTransitionException::class, 'wajib disertai alasan');

    $service->to($do, 'sent', ['override_negative_stock' => true, 'reason' => 'Stok fisik belum diinput, barang sudah ada di gudang']);

    $log = DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'sent')->first();
    expect($do->fresh()->status)->toBe('sent')
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(-4.0)
        ->and($log->action)->toBe('negative_stock_override')
        ->and($log->comments)->toContain('Stok fisik belum diinput')
        ->and($log->notes)->toContain('PENGECUALIAN STOK NEGATIF');
});

it('D15 berlaku di semua pintu: update model langsung juga ditolak', function () {
    [, , , $do] = trxReady(stock: 5, qty: 12);

    expect(fn () => $do->update(['status' => 'sent']))->toThrow(DeliveryOrderTransitionException::class, 'stok fisik tidak cukup');

    expect($do->fresh()->status)->toBe('approved')->and(trxSalesMovements())->toBe(0);
});

it('D15: SO "Ambil Sendiri" tidak dapat diselesaikan bila stok fisik kurang', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 4);
    [$so] = stkSaleOrder($ctx, 10, ['tipe_pengiriman' => 'Ambil Sendiri']);

    expect(fn () => $so->update(['status' => 'completed']))->toThrow(DeliveryOrderTransitionException::class, 'Ambil Sendiri');

    expect($so->fresh()->status)->toBe('approved')
        ->and(trxSalesMovements())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(4.0);

    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    $so->update(['status' => 'completed']);

    expect($so->fresh()->status)->toBe('completed')
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(0.0);
});

// ------------------------------------------------------------------ semua pintu = hasil sama

it('semua pintu: update model langsung, layanan lama, dan Transitions menghasilkan status item & log yang sama', function () {
    $results = [];
    foreach (['model', 'service', 'transitions'] as $door) {
        [, , , $do] = trxReady();

        match ($door) {
            'model' => $do->update(['status' => 'sent']),
            'service' => app(DeliveryOrderService::class)->updateStatus($do, 'sent', 'catatan uji'),
            'transitions' => app(DeliveryOrderTransitions::class)->to($do, 'sent'),
        };

        $results[$door] = [
            $do->fresh()->status,
            $do->fresh()->deliveryOrderItem->pluck('status')->all(),
            DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'sent')->count(),
        ];
    }

    expect($results['model'])->toBe(['sent', ['sent'], 1])
        ->and($results['service'])->toBe($results['model'])
        ->and($results['transitions'])->toBe($results['model']);
});

it('DeliveryOrderService::updateStatus mengikuti matriks dan menyimpan komentar di log', function () {
    [, , , $do] = trxReady();

    expect(fn () => app(DeliveryOrderService::class)->updateStatus($do, 'completed'))->toThrow(DeliveryOrderTransitionException::class);

    app(DeliveryOrderService::class)->updateStatus($do, 'sent', 'berangkat pagi', 'sent');
    $log = DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'sent')->first();

    expect($log->comments)->toBe('berangkat pagi')->and($log->action)->toBe('sent');
});

it('konfirmasi gudang terlambat tidak menarik DO yang sudah Dikirim kembali ke Siap Kirim', function () {
    [, , , $do] = trxReady();
    app(DeliveryOrderTransitions::class)->to($do, 'sent');

    $do->fresh()->updateStatusFromWarehouseConfirmations();   // tanpa WC → dulu memaksa status ke request_stock

    expect($do->fresh()->status)->toBe('sent');
});

// ------------------------------------------------------------------ D18 gagal kirim

it('D18: gagal kirim setelah Dikirim mengembalikan stok TEPAT SEKALI, reservasi dibuat ulang, kirim ulang memotong sekali', function () {
    [$ctx, $so, , $do] = trxReady();
    $service = app(DeliveryOrderTransitions::class);

    $service->to($do, 'sent');
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);

    expect(fn () => $service->to($do, 'delivery_failed'))->toThrow(DeliveryOrderTransitionException::class, 'Alasan pengiriman gagal wajib');

    $service->to($do, 'delivery_failed', ['reason' => 'Customer tidak di tempat']);
    $service->to($do, 'delivery_failed', ['reason' => 'Customer tidak di tempat']);   // klik ganda

    $after = stkStock($ctx['product'], $ctx['warehouse']);
    expect($do->fresh()->status)->toBe('delivery_failed')
        ->and($after['available'])->toBe(30.0)                    // kembali penuh, bukan 42
        ->and($after['reserved'])->toBe(12.0)                     // reservasi dibuat ulang untuk penjadwalan ulang
        ->and(stkReserved($so, $do))->toBe(12.0)
        ->and($do->fresh()->deliveryOrderItem->first()->status)->toBe('confirmed')
        ->and(StockMovement::where('type', 'adjustment_in')->count())->toBe(1)
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'delivery_failed')->first()->comments)->toBe('Customer tidak di tempat');

    $service->to($do, 'sent');   // dijadwalkan ulang

    $resent = stkStock($ctx['product'], $ctx['warehouse']);
    expect($resent['available'])->toBe(18.0)                      // dipotong sekali, bukan dua kali
        ->and($resent['reserved'])->toBe(0.0)
        ->and(trxSalesMovements())->toBe(2)                       // dua gerakan keluar, satu gerakan masuk penetralnya
        ->and(stkReserved($so, $do))->toBe(0.0);
});

it('gagal kirim dari Siap Kirim (stok belum keluar) tidak menyentuh stok', function () {
    [$ctx, , , $do] = trxReady();

    app(DeliveryOrderTransitions::class)->to($do, 'delivery_failed', ['reason' => 'Alamat salah']);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 12.0, 'free' => 18.0])
        ->and(StockMovement::whereIn('type', ['sales', 'adjustment_in'])->count())->toBe(0);
});

// ------------------------------------------------------------------ D19 batalkan DO

it('D19: Batalkan DO butuh alasan, melepas reservasi, DO closed final', function () {
    [$ctx, $so, , $do] = trxReady();
    $service = app(DeliveryOrderTransitions::class);

    expect(fn () => $service->to($do, 'closed'))->toThrow(DeliveryOrderTransitionException::class, 'Alasan pembatalan');

    $service->to($do, 'closed', ['reason' => 'Customer membatalkan pesanan', 'action' => 'cancelled']);

    expect($do->fresh()->status)->toBe('closed')
        ->and(stkReserved($so, $do))->toBe(0.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 0.0, 'free' => 30.0])
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'closed')->first()->comments)->toBe('Customer membatalkan pesanan')
        ->and(fn () => $service->to($do, 'approved'))->toThrow(DeliveryOrderTransitionException::class, 'Status ini sudah final');
});

it('DO yang sudah Dikirim tidak dapat dibatalkan (harus lewat gagal kirim)', function () {
    [, , , $do] = trxReady();
    $service = app(DeliveryOrderTransitions::class);
    $service->to($do, 'sent');

    expect(fn () => $service->to($do, 'closed', ['reason' => 'salah']))->toThrow(DeliveryOrderTransitionException::class);
});

// ------------------------------------------------------------------ D23 diterima / selesai

it('D23: Diterima mencatat tanggal dan nama penerima; Selesaikan menerbitkan jurnal & invoice dan item "received"', function () {
    [, , , $do] = trxReady();
    $service = app(DeliveryOrderTransitions::class);
    $service->to($do, 'sent');

    $service->to($do, 'received', ['received_by' => 'Pak Budi', 'received_at' => '2026-09-25 10:30:00']);

    $received = $do->fresh();
    expect($received->status)->toBe('received')
        ->and($received->received_by_name)->toBe('Pak Budi')
        ->and($received->received_at->format('Y-m-d H:i'))->toBe('2026-09-25 10:30')
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'received')->first()->notes)->toContain('Diterima oleh Pak Budi');

    $service->to($do, 'completed');

    expect($do->fresh()->status)->toBe('completed')
        ->and($do->fresh()->deliveryOrderItem->first()->status)->toBe('received')
        ->and(\App\Models\Invoice::where('from_model_type', \App\Models\SaleOrder::class)->whereJsonContains('delivery_orders', $do->id)->exists())->toBeTrue()
        ->and(trxSalesMovements())->toBe(1);   // completed tidak memotong stok lagi
});

it('Selesaikan langsung dari Dikirim melewati Diterima otomatis; keduanya tercatat', function () {
    [, , , $do] = trxReady();
    $service = app(DeliveryOrderTransitions::class);
    $service->to($do, 'sent');

    $service->complete($do);

    $statuses = DeliveryOrderLog::where('delivery_order_id', $do->id)->orderBy('id')->pluck('status')->all();
    expect($do->fresh()->status)->toBe('completed')
        ->and($do->fresh()->received_at)->not->toBeNull()
        ->and($statuses)->toBe(['approved', 'sent', 'received', 'completed']);
});

// ------------------------------------------------------------------ jadwal (D5, D20)

it('D5: jadwal tidak dapat ditandai selesai dari pending — di model (tombol maupun form status)', function () {
    [$ctx, , , $do] = trxReady();
    $schedule = stkSchedule($ctx, $do);

    expect(fn () => $schedule->update(['status' => 'delivered']))->toThrow(DeliveryOrderTransitionException::class, 'Mulai Pengiriman');

    expect($schedule->fresh()->status)->toBe('pending')
        ->and($do->fresh()->status)->toBe('approved')
        ->and(trxSalesMovements())->toBe(0);
});

it('jadwal Mulai → DO Dikirim (stok keluar); Tandai Selesai → DO Selesai lewat Diterima otomatis', function () {
    [$ctx, , , $do] = trxReady();
    $schedule = stkSchedule($ctx, $do);

    $schedule->update(['status' => 'on_the_way']);
    expect($do->fresh()->status)->toBe('sent')
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);

    $schedule->update(['status' => 'delivered']);

    expect($do->fresh()->status)->toBe('completed')
        ->and($do->fresh()->deliveryOrderItem->first()->status)->toBe('received')
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->orderBy('id')->pluck('status')->all())->toBe(['approved', 'sent', 'received', 'completed'])
        ->and(trxSalesMovements())->toBe(1);
});

it('D15 di jadwal: Mulai ditolak bila stok fisik kurang; jadwal tetap pending dan DO tidak berubah', function () {
    [$ctx, , , $do] = trxReady(stock: 5, qty: 12);
    $schedule = stkSchedule($ctx, $do);

    expect(fn () => $schedule->update(['status' => 'on_the_way']))->toThrow(DeliveryOrderTransitionException::class, 'stok fisik tidak cukup');

    expect($schedule->fresh()->status)->toBe('pending')
        ->and($do->fresh()->status)->toBe('approved')
        ->and(trxSalesMovements())->toBe(0);
});

it('Mulai ditolak bila DO terkait belum Siap Kirim', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'request_stock');
    $schedule = stkSchedule($ctx, $do);

    expect(fn () => $schedule->update(['status' => 'on_the_way']))->toThrow(DeliveryOrderTransitionException::class, 'Menunggu Konfirmasi Stok');

    expect($schedule->fresh()->status)->toBe('pending');
});

it('D20: jadwal ditandai Gagal setelah berangkat → DO Pengiriman Gagal dan stok kembali, alasan tercatat', function () {
    [$ctx, , , $do] = trxReady();
    $schedule = stkSchedule($ctx, $do);
    $schedule->update(['status' => 'on_the_way']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);

    $schedule->transitionReason = 'Jalan banjir';
    $schedule->update(['status' => 'failed']);

    $log = DeliveryOrderLog::where('delivery_order_id', $do->id)->where('status', 'delivery_failed')->first();
    expect($do->fresh()->status)->toBe('delivery_failed')
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(30.0)
        ->and($log->comments)->toContain('ditandai gagal')->and($log->comments)->toContain('Jalan banjir');
});

it('jadwal dari alur lama pending → failed tanpa berangkat tidak mengubah DO', function () {
    [$ctx, , , $do] = trxReady();
    $schedule = stkSchedule($ctx, $do);

    $schedule->update(['status' => 'failed']);

    expect($do->fresh()->status)->toBe('approved');
});

it('helper UI jadwal: penolakan menjadi notifikasi, bukan galat, dan status tidak berubah', function () {
    [$ctx, , , $do] = trxReady();
    $schedule = stkSchedule($ctx, $do);

    \App\Filament\Resources\DeliveryScheduleResource::changeScheduleStatus($schedule, 'delivered');

    expect($schedule->fresh()->status)->toBe('pending');
});

it('form Buat Jadwal: status selain Menunggu dipaksa Menunggu Keberangkatan (D5)', function () {
    $page = new \App\Filament\Resources\DeliveryScheduleResource\Pages\CreateDeliverySchedule;
    $mutate = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
    $mutate->setAccessible(true);

    $data = $mutate->invoke($page, ['status' => 'delivered', 'delivery_method' => 'ekspedisi', 'driver_name' => 'JNE', 'schedule_number' => 'SCH-X']);

    expect($data['status'])->toBe('pending');
});

// ------------------------------------------------------------------ perintah & flag mati

it('delivery-orders:resync-item-status melaporkan lalu memperbaiki status item yang tertinggal', function () {
    config(['sales.stock.strict_dispatch' => false]);
    [, , , $do] = trxReady();
    $do->update(['status' => 'sent']);
    $do->deliveryOrderItem()->update(['status' => 'requested']);   // seperti data lama

    $this->artisan('delivery-orders:resync-item-status')->expectsOutputToContain('Terdeteksi: 1 item')->assertSuccessful();
    expect($do->fresh()->deliveryOrderItem->first()->status)->toBe('requested');

    $this->artisan('delivery-orders:resync-item-status', ['--apply' => true])->expectsOutputToContain('Diperbaiki: 1 item')->assertSuccessful();
    expect($do->fresh()->deliveryOrderItem->first()->status)->toBe('sent');

    $this->artisan('delivery-orders:resync-item-status')->expectsOutputToContain('sudah selaras')->assertSuccessful();
});

it('[flag mati] model tidak menegakkan matriks dan tidak menulis log baru (perilaku lama utuh)', function () {
    config(['sales.stock.strict_dispatch' => false]);
    [, , , $do] = trxReady();

    $do->update(['status' => 'completed']);   // approved → completed langsung (dulu boleh)

    expect($do->fresh()->status)->toBe('completed')
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->count())->toBe(0);
});
