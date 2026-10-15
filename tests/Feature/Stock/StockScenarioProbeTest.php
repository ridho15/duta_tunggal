<?php

/**
 * T2.0 — skenario probe audit (docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md §2) sebagai regresi PERMANEN.
 *
 * Bagian 1 mengunci perilaku LAMA (semua flag `sales.stock.*` mati) — bukti bahwa flag mati = tidak ada perubahan perilaku.
 * Setiap tugas T2 menambahkan pasangan tes "flag hidup" pada berkas ini/berkas Stock lain.
 */

use App\Models\StockMovement;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => ['require_surat_jalan' => false, 'ledger' => false, 'strict_dispatch' => false, 'reserve_on_so_approve' => false, 'block_short_approval' => false]]);
});

it('[flag mati] semua flag stok default mati di berkas config', function () {
    $defaults = require config_path('sales.php');

    // `require_surat_jalan` sengaja hidup secara default (alur Ko Handy: SJ wajib sebelum DO dikirim/selesai); keempat flag stok lain mati.
    expect(\Illuminate\Support\Arr::except($defaults['stock'], ['require_surat_jalan']))->toBe(['ledger' => false, 'strict_dispatch' => false, 'reserve_on_so_approve' => false, 'block_short_approval' => false])
        ->and($defaults['stock']['require_surat_jalan'])->toBeTrue();
});

it('[flag mati] SO qty 35 dengan stok 30 lolos approve tanpa peringatan dan tanpa reservasi (celah usulan 2)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so] = stkSaleOrder($ctx, 35, ['status' => 'request_approve']);

    expect(app(SalesOrderService::class)->approve($so))->toBeTrue();

    expect($so->fresh()->status)->toBe('approved')
        ->and(stkReserved($so))->toBe(0.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 0.0, 'free' => 30.0]);
});

it('[flag mati] DO yang gagal setelah Dikirim lalu dikirim ulang TIDAK memotong stok dua kali (F6 teratasi, anti-regresi)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    $do->update(['status' => 'sent']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);

    // Flag mati: gagal kirim tidak mengembalikan stok (perilaku lama yang dipertahankan)...
    $do->update(['status' => 'delivery_failed']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);

    // ...tetapi sejak Tahap 3 pemotongan idempoten: kirim ulang TIDAK memotong ganda (dulu menjadi 6 / 2 gerakan)
    $do->update(['status' => 'sent']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0)
        ->and(StockMovement::where('type', 'sales')->count())->toBe(1);
});
