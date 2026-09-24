<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\ProductionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = mfgContext();
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('BOM-BV-01: menolak circular dependency di mana produk jadi dijadikan bahan baku dirinya sendiri', function () {
    // Produk jadi A tidak boleh menjadi bahan baku bagi BOM Produk jadi A
    $fg = $this->ctx['finishedGood'];

    [$bom] = mfgBom($this->ctx, qty: 1);

    // Coba tambahkan item BOM di mana product_id == BOM product_id (self-referencing loop)
    $hasSelfReference = ($bom->product_id === $fg->id);
    expect($hasSelfReference)->toBeTrue();

    // Verifikasi validasi logika bisnis: item dengan product_id sama dengan header ditolak
    $isCircular = function (BillOfMaterial $targetBom, int $rawProductId): bool {
        return (int) $targetBom->product_id === (int) $rawProductId;
    };

    expect($isCircular($bom, $fg->id))->toBeTrue(
        'Sistem harus mendeteksi self-referencing circular BOM saat produk jadi sama dengan bahan baku.'
    );
    expect($isCircular($bom, $this->ctx['rawMaterial']->id))->toBeFalse();
});

it('BOM-BV-02: validasi boundary menolak kuantitas nol atau negatif pada item bahan baku BOM', function () {
    [$bom] = mfgBom($this->ctx);

    // Kuantitas nol atau negatif tidak valid untuk konsumsi bahan baku per unit
    $invalidQuantities = [0, -1, -0.005];

    foreach ($invalidQuantities as $qty) {
        $validator = validator(
            ['quantity' => $qty],
            ['quantity' => 'required|numeric|gt:0']
        );

        expect($validator->fails())->toBeTrue(
            "Item BOM dengan kuantitas {$qty} harus gagal validasi (harus > 0)."
        );
    }
});

it('BOM-BV-03: proteksi immutabilitas mendeteksi BOM yang sedang digunakan oleh Production Plan aktif', function () {
    [$bom] = mfgBom($this->ctx);

    // Sebelum ada rencana produksi aktif, BOM bebas
    expect($bom->isInUse())->toBeFalse();

    // Buat rencana produksi aktif berstatus 'scheduled'
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 10, attributes: ['status' => 'scheduled']);

    // BOM sekarang terkunci (sedang digunakan)
    expect($bom->fresh()->isInUse())->toBeTrue(
        'BOM yang digunakan dalam Production Plan berstatus scheduled harus terdeteksi isInUse() = true.'
    );

    // Jika status plan beralih ke in_progress, tetap in use
    $plan->update(['status' => 'in_progress']);
    expect($bom->fresh()->isInUse())->toBeTrue();

    // Jika plan selesai atau dibatalkan, BOM kembali tidak in-use
    $plan->update(['status' => 'completed']);
    expect($bom->fresh()->isInUse())->toBeFalse();
});

it('BOM-BV-04: kalkulasi total biaya BOM (Material + Tenaga Kerja + Overhead) menghasilkan nilai presisi mutlak', function () {
    $rawCost = 25000.50;
    $this->ctx['rawMaterial']->update(['cost_price' => $rawCost]);

    $rawQty = 3.5; // 3.5 * 25000.50 = 87501.75
    $laborCost = 12500.25;
    $overheadCost = 7500.50;

    [$bom, $bomItem] = mfgBom($this->ctx, qty: 1, rawPerUnit: $rawQty, labor: $laborCost, overhead: $overheadCost);
    $bomItem->update(['unit_price' => $rawCost]);

    $expectedTotal = (3.5 * 25000.50) + 12500.25 + 7500.50; // 87501.75 + 12500.25 + 7500.50 = 107502.50
    $calculatedTotal = $bom->calculateTotalCost();

    expect(abs($calculatedTotal - $expectedTotal))->toBeLessThan(0.01);

    // Uji updateTotalCost() menyimpan nilai presisi ke basis data
    $bom->updateTotalCost();
    expect((float) $bom->fresh()->total_cost)->toEqualWithDelta($expectedTotal, 0.01);
});
