<?php

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Services\AssetDepreciationService;
use App\Services\AssetDisposalService;
use App\Services\AssetService;
use App\Services\AssetTransferService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = astContext();

    // Role 1: Staf Pengelola Aset
    $this->stafAset = User::factory()->create([
        'name' => 'Dimas Staf Aset',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'all',
    ]);
    $roleStaf = Role::firstOrCreate(['name' => 'Staf Aset', 'guard_name' => 'web']);
    $this->stafAset->assignRole($roleStaf);

    // Role 2: Supervisor Keuangan
    $this->spvAset = User::factory()->create([
        'name' => 'Sari Supervisor Aset',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'all',
    ]);
    $roleSpv = Role::firstOrCreate(['name' => 'Supervisor Aset', 'guard_name' => 'web']);
    $this->spvAset->assignRole($roleSpv);

    // Role 3: Manajer Keuangan / Direksi
    $this->manajerKeuangan = User::factory()->create([
        'name' => 'Hendra Manajer Keuangan',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'all',
    ]);
    $roleMgr = Role::firstOrCreate(['name' => 'Manajer Keuangan', 'guard_name' => 'web']);
    $this->manajerKeuangan->assignRole($roleMgr);
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

/**
 * ADV-AST-01: Staf Aset dilarang memposting jurnal akuisisi ganda pada aset yang sudah memiliki jurnal
 */
it('ADV-AST-01: Sistem memblokir pembukuan jurnal akuisisi ganda (anti-duplicate acquisition) pada aset yang sama', function () {
    $this->actingAs($this->stafAset);

    $assetService = app(AssetService::class);
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 50000000,
        'credit_coa_id' => $this->ctx['kasCoa']->id,
    ]);

    // Pastikan jurnal perolehan pertama sudah terbentuk (oleh AssetObserver)
    if (!$assetService->hasPostedJournals($asset)) {
        $assetService->postAssetAcquisitionJournal($asset, $this->ctx['kasCoa']->id);
    }
    expect($asset->fresh()->hasPostedJournals())->toBeTrue();

    // Percobaan posting ulang kedua kali via controller harus dicegah total
    $responseSecond = $this->post(route('keuangan.asset.post-acquisition-journal', $asset), [
        'credit_coa_id' => $this->ctx['kasCoa']->id,
    ]);
    $responseSecond->assertSessionHas('error', 'Jurnal akuisisi untuk aset ini sudah pernah diposting.');

    // Verifikasi jurnal tidak digandakan di Buku Besar (hanya ada 2 baris: Debit Aset, Kredit Kas)
    $journals = JournalEntry::withoutGlobalScopes()
        ->where('source_type', Asset::class)
        ->where('source_id', $asset->id)
        ->get();
    expect($journals->count())->toEqual(2);
    expect((float) $journals->sum('debit'))->toEqualWithDelta(50000000.0, 0.01);
    expect((float) $journals->sum('credit'))->toEqualWithDelta(50000000.0, 0.01);
});

/**
 * ADV-AST-02: Penyusutan bulanan menolak tanggal sebelum masa pakai dan membatasi underflow di bawah nilai residu
 */
it('ADV-AST-02: Sistem menolak penyusutan sebelum tanggal pemakaian dan mencegah nilai buku turun di bawah nilai sisa (underflow guard)', function () {
    $this->actingAs($this->spvAset);

    // Aset mulai digunakan bulan ini
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 10000000,
        'salvage_value' => 2000000, // Residu 2 Juta
        'useful_life_years' => 1,
        'usage_date' => now()->startOfMonth()->toDateString(),
        'status' => 'active',
    ]);

    $deprService = app(AssetDepreciationService::class);

    // Kasus A: Mencoba menyusutkan pada tanggal sebelum pemakaian (bulan lalu)
    $pastDate = now()->subMonth()->endOfMonth();
    expect(fn () => $deprService->generateMonthlyDepreciation($asset, $pastDate))
        ->toThrow(\Exception::class, 'Tanggal penyusutan tidak boleh sebelum tanggal pakai aset');

    // Kasus B: Underflow Guard saat nilai buku sudah mencapai nilai sisa
    $asset->accumulated_depreciation = 8000000; // 10 Juta - 8 Juta = 2 Juta (Nilai Sisa tercapai)
    $asset->book_value = 2000000;
    $asset->save();

    AssetDepreciation::create([
        'asset_id' => $asset->id,
        'depreciation_date' => now()->startOfMonth()->toDateString(),
        'period_month' => now()->month,
        'period_year' => now()->year,
        'amount' => 8000000,
        'accumulated_total' => 8000000,
        'book_value' => 2000000,
        'status' => 'recorded',
    ]);

    $currentDate = now()->addMonth()->endOfMonth();
    expect(fn () => $deprService->generateMonthlyDepreciation($asset, $currentDate))
        ->toThrow(\Exception::class, 'Aset sudah disusutkan penuh');
});

/**
 * ADV-AST-03: Mutasi transfer aset mengunci pengajuan ganda dan menolak completion sebelum disetujui (approval guard)
 */
it('ADV-AST-03: Mutasi aset mencegah transfer request ganda paralel dan menolak penyelesaian sebelum disetujui', function () {
    $this->actingAs($this->stafAset);

    $asset = astCreate($this->ctx, ['status' => 'active']);

    $cabangTujuan = Cabang::factory()->create([
        'kode' => 'AST-DEST-' . strtoupper(substr(uniqid(), -4)),
        'nama' => 'Cabang Penerima Aset',
        'status' => 1,
    ]);

    $transferService = app(AssetTransferService::class);

    // 1. Pengajuan transfer pertama berstatus 'pending'
    $transfer1 = $transferService->createTransferRequest($asset, $cabangTujuan->id, [
        'reason' => 'Kebutuhan operasional cabang baru',
        'transfer_date' => now()->toDateString(),
    ]);
    expect($transfer1->status)->toEqual('pending');

    // 2. Mencoba mengajukan transfer kedua saat transfer pertama masih pending harus ditolak
    expect(fn () => $transferService->createTransferRequest($asset, $cabangTujuan->id, [
        'reason' => 'Pengajuan duplikat paralel',
        'transfer_date' => now()->toDateString(),
    ]))->toThrow(\Exception::class, 'Asset sedang dalam proses transfer');

    // 3. Supervisor mencoba complete langsung saat transfer belum diapprove -> DITOLAK
    expect(fn () => $transferService->completeTransfer($transfer1))
        ->toThrow(\Exception::class, 'Transfer harus disetujui terlebih dahulu');
});

/**
 * ADV-AST-04: Aset yang telah dilepas / disposal terkunci permanen dari penyusutan baru
 */
it('ADV-AST-04: Aset berstatus disposed terkunci total dari eksekusi depresiasi lanjutan', function () {
    $this->actingAs($this->spvAset);

    $asset = astCreate($this->ctx, [
        'purchase_cost' => 60000000,
        'salvage_value' => 6000000,
        'useful_life_years' => 5,
        'status' => 'disposed', // Sudah dilepas
    ]);

    $deprService = app(AssetDepreciationService::class);

    expect(fn () => $deprService->generateMonthlyDepreciation($asset, now()->endOfMonth()))
        ->toThrow(\Exception::class, 'Aset tidak aktif');

    expect(AssetDepreciation::where('asset_id', $asset->id)->count())->toEqual(0);
});

/**
 * UAT-AST-01: Alur Lengkap End-to-End Siklus Hidup Aset (Pencatatan -> Depresiasi -> Pelepasan) dengan Pembuktian 4 Lapis
 */
it('UAT-AST-01: End-to-End Siklus Hidup Aset Tetap (Pencatatan -> Depresiasi Bulanan -> Pelepasan Penjualan Untung) dengan Pembuktian 4 Lapis', function () {
    // === TAHAP 1: Staf Aset Mencatat dan Mengakuisisi Kendaraan Operasional ===
    $this->actingAs($this->stafAset);

    // Beli kendaraan: Rp 120.000.000, Residu: Rp 12.000.000, Umur: 5 Tahun (60 Bulan)
    // Depresiasi per bulan = (120jt - 12jt) / 60 = Rp 1.800.000/bulan
    $asset = astCreate($this->ctx, [
        'name' => 'Truk Engkel Logistik 2026',
        'purchase_cost' => 120000000,
        'salvage_value' => 12000000,
        'useful_life_years' => 5,
        'purchase_date' => now()->startOfMonth()->toDateString(),
        'usage_date' => now()->startOfMonth()->toDateString(),
        'credit_coa_id' => $this->ctx['kasCoa']->id,
        'status' => 'active',
    ]);

    $assetService = app(AssetService::class);
    if (!$assetService->hasPostedJournals($asset)) {
        $assetService->postAssetAcquisitionJournal($asset, $this->ctx['kasCoa']->id);
    }

    $asset->refresh();
    expect($asset->status)->toEqual('active');

    // Verifikasi Jurnal Akuisisi (Debit Kendaraan Rp 120jt vs Kredit Kas Rp 120jt)
    $acqJournals = JournalEntry::withoutGlobalScopes()
        ->where('source_type', Asset::class)
        ->where('source_id', $asset->id)
        ->get();
    expect($acqJournals->count())->toEqual(2);
    expect((float) $acqJournals->sum('debit'))->toEqualWithDelta(120000000.0, 0.01);
    expect((float) $acqJournals->sum('credit'))->toEqualWithDelta(120000000.0, 0.01);


    // === TAHAP 2: Supervisor Memproses Penyusutan Bulanan Periode Berjalan ===
    $this->actingAs($this->spvAset);

    $deprDate = now()->endOfMonth();
    $deprResponse = $this->post(route('keuangan.asset.depreciation.process-single', $asset), [
        'period_month' => $deprDate->month,
        'period_year' => $deprDate->year,
    ]);
    $deprResponse->assertSessionHas('success');

    $asset->refresh();
    expect((float) $asset->accumulated_depreciation)->toEqualWithDelta(1800000.0, 0.01);
    expect((float) $asset->book_value)->toEqualWithDelta(118200000.0, 0.01); // 120jt - 1.8jt = 118.2jt

    // Jurnal Penyusutan Terbit: Debit Beban Penyusutan Rp 1.8jt, Kredit Akumulasi Penyusutan Rp 1.8jt
    $deprEntry = AssetDepreciation::where('asset_id', $asset->id)->first();
    expect($deprEntry)->not->toBeNull();
    expect((float) $deprEntry->amount)->toEqualWithDelta(1800000.0, 0.01);


    // === TAHAP 3: Manajer Keuangan Menyetujui Pelepasan (Disposal) Penjualan Untung ===
    $this->actingAs($this->manajerKeuangan);

    // Nilai buku saat ini = Rp 118.200.000. Dijual tunai dengan harga Rp 119.000.000 (Laba Penjualan Aset = Rp 800.000)
    $disposalPayload = [
        'asset_id' => $asset->id,
        'disposal_date' => now()->toDateString(),
        'disposal_type' => 'sale',
        'sale_price' => 119000000,
        'notes' => 'Pelepasan unit kendaraan operasional ke mitra dengan keuntungan',
    ];

    $disposalResponse = $this->post(route('keuangan.asset.disposal.store'), $disposalPayload);
    $disposalResponse->assertSessionHas('success');

    $asset->refresh();
    expect($asset->status)->toEqual('disposed');

    $disposal = AssetDisposal::where('asset_id', $asset->id)->first();
    expect($disposal)->not->toBeNull();
    expect((float) $disposal->gain_loss_amount)->toEqualWithDelta(800000.0, 0.01); // Keuntungan Rp 800.000


    // === TAHAP 4: Verifikasi Integritas 4 Lapis Data ===
    // [LAPIS 1: Status Dokumen Transaksi]
    expect($asset->status)->toEqual('disposed');
    expect($deprEntry->status)->toEqual('recorded');
    expect($disposal->exists)->toBeTrue();

    // [LAPIS 2: Kartu Riwayat Aset & Nilai Buku Akurat]
    expect((float) $disposal->book_value_at_disposal)->toEqualWithDelta(118200000.0, 0.01);
    expect((float) $disposal->sale_price)->toEqualWithDelta(119000000.0, 0.01);

    // [LAPIS 3: Keseimbangan Jurnal Pelepasan & Zero Drift]
    $disposalJournals = JournalEntry::withoutGlobalScopes()
        ->where('source_type', AssetDisposal::class)
        ->where('source_id', $disposal->id)
        ->get();
    expect($disposalJournals->count())->toBeGreaterThanOrEqual(3);

    $totalDebitDisposal = (float) $disposalJournals->sum('debit');
    $totalCreditDisposal = (float) $disposalJournals->sum('credit');
    expect($totalDebitDisposal)->toBeGreaterThan(0);
    expect($totalCreditDisposal)->toBeGreaterThan(0);
    expect(abs($totalDebitDisposal - $totalCreditDisposal))->toBeLessThan(0.01);

    // [LAPIS 4: Eliminasi Akun & Pengakuan Keuntungan di Buku Besar]
    // Kas bertambah (Debit Kas Rp 119jt)
    $kasDebit = (float) $disposalJournals->where('coa_id', $this->ctx['kasCoa']->id)->sum('debit');
    expect($kasDebit)->toEqualWithDelta(119000000.0, 0.01);

    // Aset Kendaraan tereliminasi (Kredit Kendaraan Rp 120jt)
    $assetCredit = (float) $disposalJournals->where('coa_id', $this->ctx['assetCoa']->id)->sum('credit');
    expect($assetCredit)->toEqualWithDelta(120000000.0, 0.01);

    // Akumulasi penyusutan tereliminasi (Debit Akumulasi Rp 1.8jt)
    $accumDebit = (float) $disposalJournals->where('coa_id', $this->ctx['accumulatedDepreciationCoa']->id)->sum('debit');
    expect($accumDebit)->toEqualWithDelta(1800000.0, 0.01);

    // Pengakuan Keuntungan Pelepasan (Kredit Gain Rp 800rb)
    $gainCredit = (float) $disposalJournals->where('coa_id', $this->ctx['gainCoa']->id)->sum('credit');
    expect($gainCredit)->toEqualWithDelta(800000.0, 0.01);
});
