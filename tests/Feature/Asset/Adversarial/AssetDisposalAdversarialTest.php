<?php

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\JournalEntry;
use App\Services\AssetDepreciationService;
use App\Services\AssetDisposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = astContext();
    $this->disposalService = app(AssetDisposalService::class);
    $this->depreciationService = app(AssetDepreciationService::class);
});

test('AST-DSP-01: Pelepasan aset jenis penjualan dengan keuntungan (gain on disposal) menghasilkan jurnal seimbang', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 100000000.0,
        'salvage_value' => 10000000.0,
    ]);

    // Set akumulasi penyusutan 60 Juta -> Nilai buku saat ini = 40 Juta
    $asset->accumulated_depreciation = 60000000.0;
    $asset->book_value = 40000000.0;
    $asset->save();

    // Dijual untung seharga 55 Juta (Laba pelepasan = 15 Juta)
    $disposal = $this->disposalService->createDisposal($asset, [
        'disposal_date' => now()->toDateString(),
        'disposal_type' => 'sale',
        'sale_price' => 55000000.0,
        'notes' => 'Penjualan aset lama di atas nilai buku',
    ]);

    expect($disposal->status)->toBe('completed');
    expect((float)$disposal->gain_loss_amount)->toBe(15000000.0);
    expect($disposal->gain_loss_type)->toBe('gain');
    expect($asset->fresh()->status)->toBe('disposed');

    // Cek jurnal pelepasan
    $journals = JournalEntry::where('source_type', AssetDisposal::class)
        ->where('source_id', $disposal->id)
        ->get();

    // Dr Kas (55jt) + Dr Akumulasi Penyusutan (60jt) = 115jt
    // Cr Nilai Perolehan Aset (100jt) + Cr Pendapatan Laba Pelepasan (15jt) = 115jt
    $totalDebit = (float)$journals->sum('debit');
    $totalCredit = (float)$journals->sum('credit');

    expect($totalDebit)->toBe(115000000.0);
    expect($totalCredit)->toBe(115000000.0);
    expect($totalDebit - $totalCredit)->toBe(0.0);
});

test('AST-DSP-02: Pelepasan aset jenis scrap atau rusak total mencatat kerugian penuh dengan jurnal seimbang', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 50000000.0,
        'salvage_value' => 5000000.0,
    ]);

    // Set akumulasi penyusutan 30 Juta -> Sisa nilai buku = 20 Juta
    $asset->accumulated_depreciation = 30000000.0;
    $asset->book_value = 20000000.0;
    $asset->save();

    // Dihapus/Scrap tanpa hasil penjualan (Rugi pelepasan = -20 Juta)
    $disposal = $this->disposalService->createDisposal($asset, [
        'disposal_date' => now()->toDateString(),
        'disposal_type' => 'scrap',
        'notes' => 'Aset rusak berat karena kecelakaan operasional',
    ]);

    expect((float)$disposal->gain_loss_amount)->toBe(-20000000.0);
    expect($disposal->gain_loss_type)->toBe('loss');

    $journals = JournalEntry::where('source_type', AssetDisposal::class)
        ->where('source_id', $disposal->id)
        ->get();

    // Dr Akumulasi Penyusutan (30jt) + Dr Beban Kerugian Aset (20jt) = 50jt
    // Cr Nilai Perolehan Aset (50jt) = 50jt
    $totalDebit = (float)$journals->sum('debit');
    $totalCredit = (float)$journals->sum('credit');

    expect($totalDebit)->toBe(50000000.0);
    expect($totalCredit)->toBe(50000000.0);
    expect($totalDebit - $totalCredit)->toBe(0.0);
});

test('AST-DSP-03: Pelepasan aset jenis penjualan dengan kerugian (loss on sale) mencatat kas dan rugi berimbang', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 80000000.0,
        'salvage_value' => 8000000.0,
    ]);

    // Akumulasi penyusutan 40 Juta -> Nilai buku = 40 Juta
    $asset->accumulated_depreciation = 40000000.0;
    $asset->book_value = 40000000.0;
    $asset->save();

    // Terjual rugi seharga 25 Juta (Rugi = 25jt - 40jt = -15 Juta)
    $disposal = $this->disposalService->createDisposal($asset, [
        'disposal_date' => now()->toDateString(),
        'disposal_type' => 'sale',
        'sale_price' => 25000000.0,
        'notes' => 'Penjualan cepat di bawah nilai buku',
    ]);

    expect((float)$disposal->gain_loss_amount)->toBe(-15000000.0);
    expect($disposal->gain_loss_type)->toBe('loss');

    $journals = JournalEntry::where('source_type', AssetDisposal::class)
        ->where('source_id', $disposal->id)
        ->get();

    // Dr Kas (25jt) + Dr Akumulasi (40jt) + Dr Kerugian (15jt) = 80jt
    // Cr Nilai Perolehan Aset (80jt) = 80jt
    $totalDebit = (float)$journals->sum('debit');
    $totalCredit = (float)$journals->sum('credit');

    expect($totalDebit)->toBe(80000000.0);
    expect($totalCredit)->toBe(80000000.0);
    expect($totalDebit - $totalCredit)->toBe(0.0);
});

test('AST-DSP-04: Aset yang telah di-dispose terkunci dari penyusutan bulanan berikutnya', function () {
    $asset = astCreate($this->ctx);

    $this->disposalService->createDisposal($asset, [
        'disposal_date' => now()->toDateString(),
        'disposal_type' => 'scrap',
    ]);

    $asset->refresh();
    expect($asset->status)->toBe('disposed');

    // Usaha menyusutkan aset yang sudah dilepas harus ditolak keras
    expect(fn () => $this->depreciationService->generateMonthlyDepreciation($asset, now()))
        ->toThrow(\Exception::class, 'Aset tidak aktif');
});
