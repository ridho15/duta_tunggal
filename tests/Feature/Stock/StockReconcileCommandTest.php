<?php

/**
 * T2.1 — stock:reconcile-reservations: yatim dibersihkan (--apply, dengan cadangan), tak-terjelaskan dan stok negatif HANYA dilaporkan,
 * reservasi Material Issue tidak pernah disentuh, dry-run tidak mengubah apa pun.
 */

use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\StockReservation;
use App\Models\StockReservationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function reconDir(): string
{
    $dir = sys_get_temp_dir().'/recon-'.uniqid();
    mkdir($dir);

    return $dir;
}

function reconCsv(string $dir, string $prefix): array
{
    $files = File::glob("{$dir}/{$prefix}-*.csv");
    if ($files === []) {
        return [];
    }
    $rows = array_map('str_getcsv', file($files[0], FILE_IGNORE_NEW_LINES));
    $rows[0][0] = ltrim($rows[0][0], "\xEF\xBB\xBF");
    $header = array_shift($rows);

    return array_map(fn ($r) => array_combine($header, $r), $rows);
}

function reconFingerprint(): array
{
    return [
        'stocks' => md5(json_encode(DB::table('inventory_stocks')->orderBy('id')->get()->all())),
        'reservations' => md5(json_encode(DB::table('stock_reservations')->orderBy('id')->get()->all())),
        'events' => DB::table('stock_reservation_events')->count(),
    ];
}

/**
 * Data uji: stok 60; SO 40 berjalan; DO A (Siap Kirim, reservasi 5 — SAH); DO B sudah Dikirim tetapi reservasi 12 tak pernah dilepas (YATIM, perilaku lama);
 * SO lain dibatalkan dengan sisa reservasi 3 (YATIM); reservasi Material Issue 7 (tak boleh disentuh).
 */
function reconScenario(): array
{
    config(['sales.stock.ledger' => false]);   // perilaku lama menghasilkan yatim
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 60);
    [$so, $item] = stkSaleOrder($ctx, 40);

    $valid = stkDeliveryOrder($ctx, $so, $item, 5, 'approved');
    $leaky = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    $leaky->update(['status' => 'sent']);            // stok fisik keluar; reservasi 12 tetap (yatim)

    [$canceledSo] = stkSaleOrder($ctx, 3, ['status' => 'canceled']);
    StockReservation::create(['sale_order_id' => $canceledSo->id, 'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 3]);

    $materialIssue = MaterialIssue::factory()->create(['warehouse_id' => $ctx['warehouse']->id]);
    StockReservation::create(['material_issue_id' => $materialIssue->id, 'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 7]);

    return compact('ctx', 'so', 'valid', 'leaky', 'canceledSo', 'materialIssue');
}

it('dry-run melaporkan yatim (12 + 3) tanpa mengubah apa pun', function () {
    $s = reconScenario();
    $before = reconFingerprint();
    $dir = reconDir();

    expect(stkStock($s['ctx']['product'], $s['ctx']['warehouse'])['reserved'])->toBe(27.0);   // 5 + 12 + 3 + 7

    expect(Artisan::call('stock:reconcile-reservations', ['--out-dir' => $dir]))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('[DRY-RUN')->toContain('Reservasi yatim: 2 baris')->toContain('total 15 unit')->toContain('Dry-run: jalankan dengan --apply')
        ->and(reconFingerprint())->toBe($before);

    $rows = collect(reconCsv($dir, 'stock-reservation-orphans'));
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('sebab')->implode(' | '))->toContain('DO sent (reservasi tak pernah dilepas)')->toContain('SO canceled');

    File::deleteDirectory($dir);
});

it('--apply menghapus HANYA yatim, menurunkan qty_reserved tepat sebesar itu, menulis cadangan dan event; SAH + Material Issue utuh', function () {
    $s = reconScenario();
    $dir = reconDir();

    Artisan::call('stock:reconcile-reservations', ['--apply' => true, '--out-dir' => $dir]);

    expect(Artisan::output())->toContain('[APPLY]')->toContain('Dihapus: 2 reservasi yatim');

    // 27 − 15 = 12: reservasi DO A (5) + Material Issue (7)
    expect(stkStock($s['ctx']['product'], $s['ctx']['warehouse']))->toBe(['available' => 48.0, 'reserved' => 12.0, 'free' => 36.0])
        ->and(StockReservation::where('delivery_order_id', $s['valid']->id)->count())->toBe(1)
        ->and(StockReservation::where('material_issue_id', $s['materialIssue']->id)->count())->toBe(1)
        ->and(StockReservation::where('delivery_order_id', $s['leaky']->id)->count())->toBe(0)
        ->and(StockReservation::where('sale_order_id', $s['canceledSo']->id)->count())->toBe(0)
        ->and(StockReservationEvent::where('event', 'reconciled')->count())->toBe(2);

    $backup = reconCsv($dir, 'stock-reservation-backup');
    expect($backup)->toHaveCount(2)
        ->and((float) $backup[0]['stok_fisik_sebelum'])->toBe(48.0)
        ->and((float) $backup[0]['qty_reserved_sebelum'])->toBe(27.0);

    File::deleteDirectory($dir);
});

it('--apply idempoten: menjalankan lagi tidak menemukan yatim dan tidak mengubah apa pun', function () {
    reconScenario();
    $dir = reconDir();
    Artisan::call('stock:reconcile-reservations', ['--apply' => true, '--out-dir' => $dir]);
    $after = reconFingerprint();

    Artisan::call('stock:reconcile-reservations', ['--apply' => true, '--out-dir' => $dir]);

    expect(Artisan::output())->toContain('Reservasi yatim: 0 baris')->and(reconFingerprint())->toBe($after);

    File::deleteDirectory($dir);
});

it('qty_reserved TAK TERJELASKAN hanya dilaporkan (D17) — tidak pernah diubah, dan tidak bercampur dengan yatim', function () {
    $s = reconScenario();
    $dir = reconDir();
    // + 9 tanpa reservasi apa pun (mis. impor legacy / Retur Pembelian)
    InventoryStock::where('product_id', $s['ctx']['product']->id)->where('warehouse_id', $s['ctx']['warehouse']->id)->increment('qty_reserved', 9);

    Artisan::call('stock:reconcile-reservations', ['--apply' => true, '--out-dir' => $dir]);
    $output = Artisan::output();

    // yatim (15) dibersihkan; sisa 27 + 9 − 15 = 21, reservasi aktif 12 → selisih 9 dilaporkan, TIDAK diubah
    expect(stkStock($s['ctx']['product'], $s['ctx']['warehouse'])['reserved'])->toBe(21.0)
        ->and($output)->toContain('TAK TERJELASKAN')->toContain('1 produk×gudang')->toContain('TIDAK diubah otomatis');

    $rows = reconCsv($dir, 'stock-reservation-unexplained');
    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]['selisih'])->toBe(9.0)
        ->and((float) $rows[0]['qty_reserved'])->toBe(21.0)
        ->and((float) $rows[0]['reservasi_aktif'])->toBe(12.0)
        ->and($rows[0]['petunjuk'])->toContain('impor legacy');

    File::deleteDirectory($dir);
});

it('petunjuk menyebut Retur Pembelian bila produk pernah diretur', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20, 5);   // qty_reserved 5 tanpa reservasi
    $purchaseReturnId = DB::table('purchase_returns')->insertGetId(['return_date' => now(), 'nota_retur' => 'NR-UJI-1', 'created_by' => $ctx['user']->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('purchase_return_items')->insert(['purchase_return_id' => $purchaseReturnId, 'product_id' => $ctx['product']->id, 'qty_returned' => 5, 'unit_price' => 1000, 'reason' => 'uji', 'created_at' => now(), 'updated_at' => now()]);
    $dir = reconDir();

    Artisan::call('stock:reconcile-reservations', ['--out-dir' => $dir]);

    expect(reconCsv($dir, 'stock-reservation-unexplained')[0]['petunjuk'])->toContain('Retur Pembelian');

    File::deleteDirectory($dir);
});

it('stok negatif hanya dilaporkan', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], -4);
    $dir = reconDir();
    $before = reconFingerprint();

    Artisan::call('stock:reconcile-reservations', ['--apply' => true, '--out-dir' => $dir]);

    expect(Artisan::output())->toContain('Baris stok NEGATIF (hanya dilaporkan): 1')
        ->and((float) reconCsv($dir, 'stock-negative')[0]['qty_available'])->toBe(-4.0)
        ->and(reconFingerprint())->toBe($before);

    File::deleteDirectory($dir);
});

it('--sale-order membatasi pembersihan yatim ke satu SO', function () {
    $s = reconScenario();
    $dir = reconDir();

    Artisan::call('stock:reconcile-reservations', ['--apply' => true, '--sale-order' => $s['canceledSo']->id, '--out-dir' => $dir]);

    expect(StockReservation::where('sale_order_id', $s['canceledSo']->id)->count())->toBe(0)
        ->and(StockReservation::where('delivery_order_id', $s['leaky']->id)->count())->toBe(1);   // yatim SO lain tidak disentuh

    File::deleteDirectory($dir);
});

it('DO terhapus atau SO terhapus juga dianggap yatim', function () {
    config(['sales.stock.ledger' => false]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 6, 'approved');
    // hapus DO tanpa memicu observer pelepasan (mensimulasikan data lama)
    DB::table('delivery_orders')->where('id', $do->id)->update(['deleted_at' => now()]);
    $dir = reconDir();

    Artisan::call('stock:reconcile-reservations', ['--out-dir' => $dir]);

    expect(reconCsv($dir, 'stock-reservation-orphans')[0]['sebab'])->toContain('DO terhapus');

    File::deleteDirectory($dir);
});
