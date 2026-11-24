<?php

/**
 * Keputusan Ko Handy 24 Nov 2026 — mutasi 4 tahap:
 *   Buat → Disetujui (stok asal DICADANGKAN, belum pindah) → Dikirim oleh gudang asal (boleh sebagian item & sebagian qty,
 *   boleh beberapa kali; barang di gudang transit cabang tujuan) → Diterima oleh gudang tujuan.
 * Contoh kasus: mutasi 100 batang, mobil hanya muat 70 → kirim 70, sisanya dikirim berikutnya atau ditutup.
 */

use App\Models\StockReservation;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockAvailability;
use App\Services\StockTransferService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->ctx = stkContext();
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 120);
    $this->tujuan = stkWarehouse($this->ctx, 'Gudang Tujuan');
    $this->svc = app(StockTransferService::class);
    $this->st = StockTransfer::create(['transfer_number' => 'ST-SHIP-'.uniqid(), 'from_warehouse_id' => $this->ctx['warehouse']->id,
        'to_warehouse_id' => $this->tujuan->id, 'transfer_date' => now()->toDateString(), 'status' => 'Draft']);
    $this->item = StockTransferItem::create(['stock_transfer_id' => $this->st->id, 'product_id' => $this->ctx['product']->id, 'quantity' => 100,
        'from_warehouse_id' => $this->ctx['warehouse']->id, 'to_warehouse_id' => $this->tujuan->id]);
    $this->svc->requestTransfer($this->st->fresh());
});

it('disetujui hanya mencadangkan: stok fisik asal tetap, stok bebas berkurang', function () {
    $this->svc->approveStockTransfer($this->st->fresh());

    $asal = stkStock($this->ctx['product'], $this->ctx['warehouse']);
    expect($asal['available'])->toBe(120.0)->and($asal['reserved'])->toBe(100.0)->and($asal['free'])->toBe(20.0)
        ->and(stkStock($this->ctx['product'], $this->tujuan)['available'])->toBe(0.0);
});

it('mobil hanya muat 70 dari 100: kirim 70 → asal turun 70, barang di transit; terima → tujuan +70; sisa 30 tetap dicadangkan', function () {
    $this->svc->approveStockTransfer($this->st->fresh());
    $shipment = $this->svc->shipTransfer($this->st->fresh(), [$this->item->id => 70], 'Mobil penuh');

    $transit = $this->svc->transitWarehouse($this->tujuan->cabang_id);
    expect(stkStock($this->ctx['product'], $this->ctx['warehouse']))->toMatchArray(['available' => 50.0, 'reserved' => 30.0])
        ->and(stkStock($this->ctx['product'], $transit)['available'])->toBe(70.0)
        ->and($this->st->fresh()->status)->toBe('Shipped')
        ->and($this->st->fresh()->statusLabel())->toBe('Dikirim Sebagian');

    // Barang dalam perjalanan bukan stok jual
    expect(app(StockAvailability::class)->accessibleWarehouseIds(null))->not->toContain($transit->id);

    $this->svc->receiveShipment($shipment);
    expect(stkStock($this->ctx['product'], $this->tujuan)['available'])->toBe(70.0)
        ->and(stkStock($this->ctx['product'], $transit)['available'])->toBe(0.0)
        ->and($this->item->fresh()->remainingToShip())->toBe(30.0)
        ->and($this->st->fresh()->status)->toBe('Shipped');   // sisa 30 belum dikirim
});

it('qty kirim melebihi sisa ditolak dengan pesan sebab & solusi, tidak ada stok bergerak', function () {
    $this->svc->approveStockTransfer($this->st->fresh());

    expect(fn () => $this->svc->shipTransfer($this->st->fresh(), [$this->item->id => 101]))
        ->toThrow(ValidationException::class, 'melebihi sisa');
    expect(stkStock($this->ctx['product'], $this->ctx['warehouse'])['available'])->toBe(120.0);
});

it('belum disetujui tidak bisa dikirim', function () {
    expect(fn () => $this->svc->shipTransfer($this->st->fresh(), [$this->item->id => 10]))
        ->toThrow(ValidationException::class, 'tidak bisa dikirim');
});

it('tutup sisa setelah kirim 70 & diterima: cadangan 30 dilepas, mutasi Selesai', function () {
    $this->svc->approveStockTransfer($this->st->fresh());
    $shipment = $this->svc->shipTransfer($this->st->fresh(), [$this->item->id => 70]);
    $this->svc->receiveShipment($shipment);
    $this->svc->closeRemaining($this->st->fresh(), 'Mobil tidak muat, sisa batal');

    expect($this->st->fresh()->status)->toBe('Completed')
        ->and(stkStock($this->ctx['product'], $this->ctx['warehouse']))->toMatchArray(['available' => 50.0, 'reserved' => 0.0])
        ->and(StockReservation::where('stock_transfer_item_id', $this->item->id)->count())->toBe(0);
});

it('tutup sisa sebelum ada pengiriman: mutasi Dibatalkan dan cadangan dilepas', function () {
    $this->svc->approveStockTransfer($this->st->fresh());
    $this->svc->closeRemaining($this->st->fresh(), 'Batal dikirim');

    expect($this->st->fresh()->status)->toBe('Cancelled')
        ->and(stkStock($this->ctx['product'], $this->ctx['warehouse'])['reserved'])->toBe(0.0);
});

it('void setelah kirim sebagian (belum diterima): stok asal pulih, transit kosong, cadangan dilepas', function () {
    $this->svc->approveStockTransfer($this->st->fresh());
    $this->svc->shipTransfer($this->st->fresh(), [$this->item->id => 70]);
    $this->svc->voidStockTransfer($this->st->fresh(), 'Salah gudang tujuan');

    $transit = $this->svc->transitWarehouse($this->tujuan->cabang_id);
    expect($this->st->fresh()->status)->toBe('Cancelled')
        ->and(stkStock($this->ctx['product'], $this->ctx['warehouse']))->toMatchArray(['available' => 120.0, 'reserved' => 0.0])
        ->and(stkStock($this->ctx['product'], $transit)['available'])->toBe(0.0);
});

it('pengiriman yang sudah diterima tidak bisa diterima dua kali', function () {
    $this->svc->approveStockTransfer($this->st->fresh());
    $shipment = $this->svc->shipTransfer($this->st->fresh(), [$this->item->id => 100]);
    $this->svc->receiveShipment($shipment);

    expect($this->st->fresh()->status)->toBe('Completed');
    expect(fn () => $this->svc->receiveShipment($shipment->fresh()))->toThrow(ValidationException::class, 'sudah diterima');
    expect(stkStock($this->ctx['product'], $this->tujuan)['available'])->toBe(100.0);
});
