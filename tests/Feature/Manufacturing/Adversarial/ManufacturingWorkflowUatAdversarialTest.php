<?php

use App\Models\BillOfMaterial;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Permission;
use App\Models\Production;
use App\Models\QualityControl;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = mfgContext();

    // Pastikan permission standar manufaktur terdaftar
    $perms = [
        'view manufacturing order',
        'create manufacturing order',
        'update manufacturing order',
        'delete manufacturing order',
        'request manufacturing order',
        'view material issue',
        'create material issue',
        'update material issue',
        'view production',
        'create production',
        'update production',
    ];
    foreach ($perms as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    // Role 1: Kepala Produksi / Supervisor Pabrik
    $this->supervisor = User::factory()->create([
        'name' => 'Budi Supervisor Produksi',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang',
    ]);
    $roleSpv = Role::firstOrCreate(['name' => 'Kepala Produksi', 'guard_name' => 'web']);
    $roleSpv->givePermissionTo($perms);
    $this->supervisor->assignRole($roleSpv);

    // Role 2: Checker / Petugas Gudang Bahan Baku
    $this->checkerGudang = User::factory()->create([
        'name' => 'Joko Checker Bahan Baku',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang',
    ]);
    $roleChecker = Role::firstOrCreate(['name' => 'Petugas Gudang Bahan Baku', 'guard_name' => 'web']);
    $roleChecker->givePermissionTo(['view material issue', 'create material issue', 'update material issue']);
    $this->checkerGudang->assignRole($roleChecker);

    // Role 3: Operator Produksi / QC Manufaktur
    $this->qcOperator = User::factory()->create([
        'name' => 'Rina QC Manufaktur',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang',
    ]);
    $roleQc = Role::firstOrCreate(['name' => 'QC Manufaktur', 'guard_name' => 'web']);
    $roleQc->givePermissionTo(['view production', 'create production', 'update production']);
    $this->qcOperator->assignRole($roleQc);
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

/**
 * ADV-MFG-01: Kepala Produksi dilarang memulai SPK (in_progress) jika stok bahan baku tidak mencukupi
 */
it('ADV-MFG-01: Kepala Produksi dilarang memulai SPK jika stok bahan baku di gudang defisit / tidak mencukupi', function () {
    $this->actingAs($this->supervisor);

    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2); // 1 FG butuh 2 Raw Material

    // Rencana produksi 100 FG butuh 200 Raw Material, sementara stok gudang hanya 100
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 100);
    $mo = mfgOrder($this->ctx, $plan, [
        'status' => 'draft',
        'items' => [
            [
                'product_id' => $this->ctx['rawMaterial']->id,
                'quantity' => 200,
                'notes' => 'Kebutuhan 200 pcs plat tembaga',
            ],
        ],
    ]);

    // Verifikasi stok tersedia saat ini hanya 100 pcs
    $currentStock = InventoryStock::where('product_id', $this->ctx['rawMaterial']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->value('qty_available');
    expect((float) $currentStock)->toEqual(100.0);

    // Request start SPK
    $response = $this->post(route('manufaktur.spk.start', $mo));

    // Harus dialihkan kembali dengan flash error
    $response->assertSessionHas('error', 'Stok bahan baku di gudang tidak mencukupi untuk memulai SPK ini.');

    // Status SPK tetap 'draft' (tidak berubah menjadi in_progress)
    expect($mo->fresh()->status)->toEqual('draft');
});

/**
 * ADV-MFG-02: Checker Gudang dilarang membuat pengeluaran bahan dengan kuantitas non-positif
 */
it('ADV-MFG-02: Form pengeluaran bahan baku menolak kuantitas nol atau bernilai negatif', function () {
    $this->checkerGudang->givePermissionTo(['create material issue']);
    $this->actingAs($this->checkerGudang);

    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 10);
    $mo = mfgOrder($this->ctx, $plan, ['status' => 'draft']);

    // Kirim form StoreMaterialIssueRequest dengan quantity 0 dan negatif
    $invalidPayloads = [
        ['quantity' => 0],
        ['quantity' => -5],
    ];

    foreach ($invalidPayloads as $payload) {
        $data = [
            'issue_number' => 'MI-TEST-' . rand(1000, 9999),
            'production_plan_id' => $plan->id,
            'manufacturing_order_id' => $mo->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'issue_date' => now()->toDateString(),
            'type' => 'issue',
            'items' => [
                [
                    'product_id' => $this->ctx['rawMaterial']->id,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                    'quantity' => $payload['quantity'],
                ],
            ],
        ];

        $response = $this->post(route('manufaktur.material-issue.store'), $data);
        $response->assertSessionHasErrors('items.0.quantity');
    }

    // Tidak ada MaterialIssue yang terbuat
    expect(MaterialIssue::where('production_plan_id', $plan->id)->count())->toEqual(0);
});

/**
 * ADV-MFG-03: SPK yang sudah selesai (completed) terkunci dari pembatalan maupun pengubahan
 */
it('ADV-MFG-03: SPK yang telah selesai (completed) terkunci secara permanen dari pembatalan maupun pengubahan', function () {
    $this->actingAs($this->supervisor);

    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);
    $mo = mfgOrder($this->ctx, $plan, ['status' => 'completed']);

    // 1. Coba batalkan SPK yang sudah completed
    $cancelResponse = $this->post(route('manufaktur.spk.cancel', $mo));
    $cancelResponse->assertSessionHas('error', 'SPK yang sudah selesai tidak dapat dibatalkan.');
    expect($mo->fresh()->status)->toEqual('completed');

    // 2. Coba perbarui data SPK yang sudah completed
    $updateResponse = $this->put(route('manufaktur.spk.update', $mo), [
        'mo_number' => $mo->mo_number,
        'quantity' => 10,
    ]);
    $updateResponse->assertSessionHas('error', 'Hanya SPK berstatus Draft yang dapat diperbarui.');
    expect($mo->fresh()->status)->toEqual('completed');
});

/**
 * ADV-MFG-04: Operator QC mengisolasi unit cacat (rejected) agar tidak menambah stok barang jadi
 */
it('ADV-MFG-04: Inspeksi QC Manufaktur mengisolasi produk reject sehingga stok fisik hanya bertambah sesuai passed quantity', function () {
    $this->actingAs($this->qcOperator);

    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2, labor: 5000, overhead: 3000);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 10);
    $mo = mfgOrder($this->ctx, $plan, ['status' => 'in_progress']);

    // Terbitkan pengeluaran bahan baku 20 unit completed
    [$issue] = mfgMaterialIssue($this->ctx, $plan, rawQty: 20);
    mfgConfirmMaterialIssue($issue);
    $issue->update([
        'status' => 'completed',
        'approved_by' => $this->ctx['user']->id,
        'approved_at' => now(),
    ]);

    // Operator menyelesaikan batch produksi 10 pcs
    $production = mfgProduction($this->ctx, $mo, qtyProduced: 10, attributes: ['status' => 'finished']);

    $qcService = app(QualityControlService::class);
    $qc = $qcService->createQCFromProduction($production);

    $initialFgStock = InventoryStock::where('product_id', $this->ctx['finishedGood']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->value('qty_available') ?? 0;

    // Boundary Test QC: Kuantitas melebihi total produksi (7 passed + 5 reject = 12 > 10)
    $excessPayload = [
        'passed_quantity' => 7,
        'rejected_quantity' => 5,
        'warehouse_id' => $this->ctx['warehouse']->id,
    ];
    $responseFail = $this->post(route('manufaktur.qc.process', $qc), $excessPayload);
    $responseFail->assertSessionHasErrors();

    // Eksekusi QC Riil: 6 Passed, 4 Rejected (Cacat retak / dimensi)
    $validPayload = [
        'passed_quantity' => 6,
        'rejected_quantity' => 4,
        'reason_reject' => 'Retak pada fitting dan deviasi dimensi ketebalan',
        'warehouse_id' => $this->ctx['warehouse']->id,
        'inspected_by' => $this->qcOperator->id,
    ];

    $responseSuccess = $this->post(route('manufaktur.qc.process', $qc), $validPayload);
    $responseSuccess->assertSessionHas('success');

    // Layer 1: Status QC telah selesai (1)
    $qc->refresh();
    expect($qc->status)->toEqual(1);
    expect($qc->passed_quantity)->toEqual(6);
    expect($qc->rejected_quantity)->toEqual(4);

    // Layer 2: Stok fisik Finished Good di gudang HANYA bertambah 6 (bukan 10)
    $afterFgStock = InventoryStock::where('product_id', $this->ctx['finishedGood']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->value('qty_available');
    expect((float) $afterFgStock)->toEqual((float) $initialFgStock + 6.0);

    // Layer 3: Mutasi stok kartu persediaan hanya mencatat kuantitas lolos
    $stockMovement = StockMovement::where('from_model_type', QualityControl::class)
        ->where('from_model_id', $qc->id)
        ->where('type', 'manufacture_in')
        ->first();
    expect($stockMovement)->not->toBeNull();
    expect((float) $stockMovement->quantity)->toEqual(6.0);
});

/**
 * UAT-MFG-01: Alur Lengkap End-to-End Manufaktur dengan Pembuktian Integritas 4 Lapis
 */
it('UAT-MFG-01: End-to-End Manufaktur (SPK -> Pengeluaran Bahan -> Produksi -> QC Barang Jadi) dengan Pembuktian 4 Lapis Data', function () {
    // === TAHAP 1: Kepala Produksi Merencanakan & Memulai SPK ===
    $this->actingAs($this->supervisor);

    // BOM: 1 unit FG butuh 2 unit Raw Material (@ Rp 20.000 = Rp 40.000)
    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2, labor: 4000, overhead: 2000);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 10);
    $mo = mfgOrder($this->ctx, $plan, [
        'status' => 'draft',
        'items' => [
            [
                'product_id' => $this->ctx['rawMaterial']->id,
                'quantity' => 20,
                'notes' => 'Kebutuhan bahan baku 20 pcs',
            ],
        ],
    ]);

    // Kepala Produksi memulai SPK karena stok bahan di gudang (100 pcs) mencukupi (butuh 20 pcs)
    $startResponse = $this->post(route('manufaktur.spk.start', $mo));
    $startResponse->assertSessionHas('success');
    expect($mo->fresh()->status)->toEqual('in_progress');


    // === TAHAP 2: Checker Gudang Mengeluarkan Bahan Baku ke Lantai Produksi ===
    $this->actingAs($this->checkerGudang);

    [$issue] = mfgMaterialIssue($this->ctx, $plan, rawQty: 20);

    // Checker gudang menyetujui konfirmasi pengeluaran bahan fisik
    // (Secara otomatis mengkonfirmasi, menyelesaikan status Material Issue, memutasi stok, & menjurnal BDP)
    $confirmResponse = $this->post(route('manufaktur.material-issue.confirm-warehouse', $issue));
    $confirmResponse->assertSessionHas('success');

    $issue->refresh();
    expect($issue->status)->toEqual(MaterialIssue::STATUS_COMPLETED);

    // Pengujian Idempotensi: Memanggil endpoint complete saat status sudah completed memberikan info idempotensi
    $completeResponse = $this->post(route('manufaktur.material-issue.complete', $issue));
    $completeResponse->assertSessionHas('info', 'Dokumen ini sudah berstatus Selesai.');

    // Pembuktian Mutasi Bahan Baku:
    // Stok fisik berkurang dari stok awal (100 pcs)
    $rawStockAfter = InventoryStock::where('product_id', $this->ctx['rawMaterial']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->value('qty_available');
    expect((float) $rawStockAfter)->toEqual(60.0);

    // Jurnal Pengeluaran Bahan Baku (BDP / WIP):
    $issueJournals = JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $issue->id)
        ->get();
    expect($issueJournals->count())->toBeGreaterThanOrEqual(2);

    $totalDebitIssue = (float) $issueJournals->sum('debit');
    $totalCreditIssue = (float) $issueJournals->sum('credit');
    expect($totalDebitIssue)->toEqualWithDelta(400000.0, 0.01);
    expect($totalCreditIssue)->toEqualWithDelta(400000.0, 0.01);
    expect(abs($totalDebitIssue - $totalCreditIssue))->toBeLessThan(0.01);


    // === TAHAP 3: Operator & QC Menyelesaikan Hasil Produksi Barang Jadi ===
    $this->actingAs($this->qcOperator);

    // Operator mencatat hasil produksi 10 unit selesai
    $production = mfgProduction($this->ctx, $mo, qtyProduced: 10, attributes: ['status' => 'draft']);
    $finishResponse = $this->post(route('manufaktur.production.finish', $production));
    $finishResponse->assertSessionHas('success');

    $production->refresh();
    expect($production->status)->toEqual('finished');

    // QC diterbitkan otomatis dari hasil produksi
    $qc = QualityControl::where('from_model_type', Production::class)
        ->where('from_model_id', $production->id)
        ->first();
    expect($qc)->not->toBeNull();

    // Petugas QC melakukan inspeksi: 10 unit lolos sempurna
    $processPayload = [
        'passed_quantity' => 10,
        'rejected_quantity' => 0,
        'warehouse_id' => $this->ctx['warehouse']->id,
        'rak_id' => $this->ctx['rak']->id,
        'inspected_by' => $this->qcOperator->id,
    ];
    $processResponse = $this->post(route('manufaktur.qc.process', $qc), $processPayload);
    $processResponse->assertSessionHas('success');


    // === TAHAP 4: Verifikasi Integritas 4 Lapis Data ===
    // [LAPIS 1: Status Dokumen Transaksi]
    expect($mo->fresh()->status)->toEqual('completed');
    expect($issue->fresh()->status)->toEqual(MaterialIssue::STATUS_COMPLETED);
    expect($production->fresh()->status)->toEqual('finished');
    expect($qc->fresh()->status)->toEqual(1);

    // [LAPIS 2: Kartu Persediaan & Mutasi Stok Fisik]
    $rawOutMovement = StockMovement::where('product_id', $this->ctx['rawMaterial']->id)
        ->where('type', 'manufacture_out')
        ->sum('quantity');
    expect((float) $rawOutMovement)->toEqual(40.0);

    $fgInMovement = StockMovement::where('product_id', $this->ctx['finishedGood']->id)
        ->where('type', 'manufacture_in')
        ->sum('quantity');
    expect((float) $fgInMovement)->toEqual(10.0);

    // [LAPIS 3: Jurnal Finansial & Zero Drift]
    $fgJournals = JournalEntry::where('source_type', QualityControl::class)
        ->where('source_id', $qc->id)
        ->get();
    expect($fgJournals->count())->toBeGreaterThanOrEqual(2);

    $totalDebitFg = (float) $fgJournals->sum('debit');
    $totalCreditFg = (float) $fgJournals->sum('credit');
    expect($totalDebitFg)->toBeGreaterThan(0);
    expect($totalCreditFg)->toBeGreaterThan(0);
    expect(abs($totalDebitFg - $totalCreditFg))->toBeLessThan(0.01);

    // [LAPIS 4: Sub-ledger Inventaris & Konsistensi Stok]
    $finalFgStock = InventoryStock::where('product_id', $this->ctx['finishedGood']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->value('qty_available');
    expect((float) $finalFgStock)->toEqual(10.0);
});
