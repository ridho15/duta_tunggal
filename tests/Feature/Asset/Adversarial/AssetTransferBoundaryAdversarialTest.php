<?php

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Cabang;
use App\Services\AssetTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = astContext();
    $this->service = app(AssetTransferService::class);

    $this->cabangB = Cabang::factory()->create([
        'kode' => 'CAB-B-' . strtoupper(substr(uniqid(), -4)),
        'nama' => 'Cabang Penerima B',
        'status' => 1,
    ]);

    $this->cabangC = Cabang::factory()->create([
        'kode' => 'CAB-C-' . strtoupper(substr(uniqid(), -4)),
        'nama' => 'Cabang Penerima C',
        'status' => 1,
    ]);
});

test('AST-TRF-01: Menolak pengajuan transfer ganda jika masih ada transfer pending atau approved', function () {
    $asset = astCreate($this->ctx);

    // Pengajuan transfer pertama ke Cabang B -> status pending
    $transfer1 = $this->service->createTransferRequest($asset, $this->cabangB->id, [
        'reason' => 'Mutasi operasional tahap 1',
    ]);
    expect($transfer1->status)->toBe('pending');

    // Pengajuan transfer kedua ke Cabang C saat transfer 1 masih pending -> HARUS DITOLAK
    expect(fn () => $this->service->createTransferRequest($asset, $this->cabangC->id, [
        'reason' => 'Mutasi liar paralel',
    ]))->toThrow(\Exception::class, 'Asset sedang dalam proses transfer');

    // Setujui transfer 1 -> status approved
    $this->service->approveTransfer($transfer1);
    expect($transfer1->fresh()->status)->toBe('approved');

    // Pengajuan transfer saat transfer 1 masih approved -> TETAP HARUS DITOLAK
    expect(fn () => $this->service->createTransferRequest($asset, $this->cabangC->id, [
        'reason' => 'Mutasi liar kedua',
    ]))->toThrow(\Exception::class, 'Asset sedang dalam proses transfer');
});

test('AST-TRF-02: Menolak approval jika transfer request tidak berstatus pending', function () {
    $asset = astCreate($this->ctx);

    $transfer = $this->service->createTransferRequest($asset, $this->cabangB->id, [
        'reason' => 'Mutasi aset',
    ]);

    // Setujui transfer pertama kali
    $this->service->approveTransfer($transfer);
    expect($transfer->fresh()->status)->toBe('approved');

    // Percobaan menyetujui ulang dokumen yang sudah approved
    expect(fn () => $this->service->approveTransfer($transfer->fresh()))
        ->toThrow(\Exception::class, 'Transfer request tidak dalam status pending');

    // Selesaikan transfer
    $this->service->completeTransfer($transfer->fresh());
    expect($transfer->fresh()->status)->toBe('completed');

    // Percobaan menyetujui dokumen yang sudah completed
    expect(fn () => $this->service->approveTransfer($transfer->fresh()))
        ->toThrow(\Exception::class, 'Transfer request tidak dalam status pending');
});

test('AST-TRF-03: Menolak completion jika transfer belum disetujui (status pending)', function () {
    $asset = astCreate($this->ctx);

    $transfer = $this->service->createTransferRequest($asset, $this->cabangB->id, [
        'reason' => 'Mutasi langsung',
    ]);

    expect($transfer->status)->toBe('pending');

    // Coba langsung selesaikan tanpa approval terlebih dahulu -> HARUS DITOLAK
    expect(fn () => $this->service->completeTransfer($transfer))
        ->toThrow(\Exception::class, 'Transfer harus disetujui terlebih dahulu');

    // Pastikan cabang aset belum berpindah
    expect($asset->fresh()->cabang_id)->toBe($this->ctx['cabang']->id);
});

test('AST-TRF-04: Menolak pembatalan pada transfer yang sudah selesai dan memvalidasi perpindahan cabang aset', function () {
    $asset = astCreate($this->ctx);

    $transfer = $this->service->createTransferRequest($asset, $this->cabangB->id, [
        'reason' => 'Mutasi sukses',
    ]);

    $this->service->approveTransfer($transfer);
    $this->service->completeTransfer($transfer->fresh());

    $transfer->refresh();
    expect($transfer->status)->toBe('completed');

    // Cabang aset harus resmi berpindah ke Cabang B
    expect($asset->fresh()->cabang_id)->toBe($this->cabangB->id);

    // Percobaan membatalkan transfer yang sudah selesai -> HARUS DITOLAK
    expect(fn () => $this->service->cancelTransfer($transfer, 'Batal sepihak'))
        ->toThrow(\Exception::class, 'Transfer tidak dapat dibatalkan');
});
