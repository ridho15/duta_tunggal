<?php

/**
 * T2.6 — E2E stok & pengiriman (semua flag hidup): SO → approve (reservasi) → DO Siap Kirim (pindah) → jadwal Mulai (stok keluar,
 * reservasi terkonsumsi) → Diterima → Selesai (jurnal/invoice) — plus alur gagal-kirim dan batal — lalu rekonsiliasi = 0 yatim.
 */

use App\Models\DeliveryOrderLog;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\DeliveryOrderTransitions;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => ['ledger' => false, 'strict_dispatch' => true, 'reserve_on_so_approve' => true, 'block_short_approval' => true]]);
});

function e2eManager(array $ctx): User
{
    Permission::firstOrCreate(['name' => 'response sales order', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Sales Manager', 'guard_name' => 'web']);
    $manager = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $manager->givePermissionTo('response sales order');
    $manager->assignRole('Sales Manager');

    return $manager;
}

it('E2E: alur penuh SO → DO → jadwal → diterima → selesai; stok, reservasi, status item, log, invoice konsisten; 0 yatim', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20, ['status' => 'request_approve']);
    Auth::login(e2eManager($ctx));

    // 1. Approve → stok bebas berkurang, fisik tetap
    app(SalesOrderService::class)->approve($so);
    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 20.0, 'free' => 10.0]);

    // 2. DO 12 Siap Kirim → reservasi pindah (total tertahan tetap 20)
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(20.0)
        ->and(stkReserved($so, $do))->toBe(12.0);

    // 3. Jadwal: Selesai dari Menunggu ditolak; Mulai → stok fisik keluar, reservasi DO terkonsumsi
    $schedule = stkSchedule($ctx, $do);
    expect(fn () => $schedule->update(['status' => 'delivered']))->toThrow(\App\Exceptions\DeliveryOrderTransitionException::class);

    $schedule->update(['status' => 'on_the_way']);
    expect($do->fresh()->status)->toBe('sent')
        ->and($do->fresh()->deliveryOrderItem->first()->status)->toBe('sent')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 8.0, 'free' => 10.0])
        ->and($so->fresh()->status)->toBe('partially_delivered');

    // 4. Diterima (tanggal + penerima) lalu Selesai (invoice)
    app(DeliveryOrderTransitions::class)->to($do, 'received', ['received_by' => 'Bu Sari']);
    expect($do->fresh()->received_by_name)->toBe('Bu Sari');

    $schedule->update(['status' => 'delivered']);
    expect($do->fresh()->status)->toBe('completed')
        ->and(Invoice::where('from_model_type', SaleOrder::class)->whereJsonContains('delivery_orders', $do->id)->exists())->toBeTrue()
        ->and(DeliveryOrderLog::where('delivery_order_id', $do->id)->orderBy('id')->pluck('status')->all())->toBe(['approved', 'sent', 'received', 'completed']);

    // 5. DO kedua (sisa 8): gagal kirim → stok kembali; kirim ulang; selesai
    $do2 = stkDeliveryOrder($ctx, $so, $item, 8, 'approved');
    $transitions = app(DeliveryOrderTransitions::class);
    $transitions->to($do2, 'sent');
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(10.0);

    $transitions->to($do2, 'delivery_failed', ['reason' => 'Toko tutup']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0)
        ->and(StockReservation::where('sale_order_id', $so->id)->sum('quantity'))->toEqual(8);

    $transitions->to($do2, 'sent');
    $transitions->complete($do2);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 10.0, 'reserved' => 0.0, 'free' => 10.0])
        ->and($so->fresh()->status)->toBe('completed')
        ->and(StockReservation::count())->toBe(0)
        ->and(StockMovement::where('type', 'sales')->count())->toBe(3)          // 12 + 8 + 8 (kirim ulang)
        ->and(StockMovement::where('type', 'adjustment_in')->count())->toBe(1);  // 8 kembali sekali

    // 6. Rekonsiliasi: tidak ada reservasi yatim / qty_reserved tak terjelaskan
    $this->artisan('stock:reconcile-reservations', ['--no-csv' => true])
        ->expectsOutputToContain('A. Reservasi yatim: 0 baris')
        ->expectsOutputToContain('TAK TERJELASKAN (hanya dilaporkan, D17): 0 produk')
        ->assertSuccessful();
});

it('E2E: SO stok kurang ditolak; backorder beralasan menahan sebagian; DO Siap Kirim dibatalkan mengembalikan kebutuhan; SO dibatalkan melepas semua', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 15);
    [$so, $item] = stkSaleOrder($ctx, 20, ['status' => 'request_approve']);
    $manager = e2eManager($ctx);
    Auth::login($manager);
    $service = app(SalesOrderService::class);

    expect(fn () => $service->approve($so))->toThrow(\Illuminate\Validation\ValidationException::class);

    $service->approveAsBackorder($so->fresh(), 'Barang tiba minggu depan');
    expect($so->fresh()->is_backorder)->toBeTrue()
        ->and((float) StockReservation::where('sale_order_id', $so->id)->sum('quantity'))->toBe(15.0);

    $do = stkDeliveryOrder($ctx, $so->fresh(), $item, 10, 'approved');
    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(15.0);

    app(DeliveryOrderTransitions::class)->to($do, 'closed', ['reason' => 'Customer menunda']);
    expect(stkReserved($so, $do))->toBe(0.0)
        ->and((float) StockReservation::where('sale_order_id', $so->id)->whereNull('delivery_order_id')->sum('quantity'))->toBe(15.0);

    // stok masuk → isi ulang penuh
    stkSetStock($ctx['product'], $ctx['warehouse'], 40, 15);
    $this->artisan('sales:top-up-reservations')->assertSuccessful();
    expect((float) StockReservation::where('sale_order_id', $so->id)->sum('quantity'))->toBe(20.0);

    $service->cancel($so->fresh());
    expect(StockReservation::count())->toBe(0)->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0);
});
