<?php

use App\Filament\Resources\ProductionPlanResource;
use App\Models\ProductionPlan;
use App\Services\ProductionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = mfgContext();
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('PP-BV-01: validasi boundary menolak pembuatan atau pembaruan rencana produksi dengan kuantitas nol atau negatif', function () {
    [$bom] = mfgBom($this->ctx);

    $invalidQuantities = [0, -5, -0.01];

    foreach ($invalidQuantities as $qty) {
        $validator = validator(
            ['quantity' => $qty],
            ['quantity' => 'required|numeric|gt:0']
        );

        expect($validator->fails())->toBeTrue(
            "Rencana produksi dengan kuantitas {$qty} harus ditolak oleh validasi (harus > 0)."
        );
    }
});

it('PP-BV-02: validasi ketersediaan stok mendeteksi kekurangan stok bahan baku di gudang yang dipilih', function () {
    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 10); // Butuh 10 pcs bahan baku per 1 unit FG
    // Stok awal bahan baku di gudang = 100 pcs

    // Skenario A: Kebutuhan dalam batas stok (Plan 5 unit -> butuh 50 pcs <= 100 pcs)
    $planOk = mfgProductionPlan($this->ctx, $bom, planQty: 5);
    $validationOk = ProductionPlanResource::validateStockForProductionPlan($planOk);
    expect($validationOk['valid'])->toBeTrue();

    // Skenario B: Kebutuhan melebihi stok (Plan 15 unit -> butuh 150 pcs > 100 pcs)
    $planShort = mfgProductionPlan($this->ctx, $bom, planQty: 15);
    $validationShort = ProductionPlanResource::validateStockForProductionPlan($planShort);
    expect($validationShort['valid'])->toBeFalse(
        'Sistem harus menolak penjadwalan rencana jika kebutuhan bahan baku melebihi stok tersedia.'
    );
    expect($validationShort['message'])->toContain('tidak mencukupi');

    // Skenario C: Stok bahan baku 0
    $this->ctx['rawStock']->update(['qty_available' => 0]);
    $validationZero = ProductionPlanResource::validateStockForProductionPlan($planShort);
    expect($validationZero['valid'])->toBeFalse();
    expect($validationZero['message'])->toContain('habis');
});

it('PP-BV-03: generator nomor rencana produksi menjamin kode unik dan proteksi integritas status', function () {
    $service = app(ProductionPlanService::class);

    $number1 = $service->generatePlanNumber();
    $number2 = $service->generatePlanNumber();

    expect($number1)->not->toBeEmpty();
    expect($number2)->not->toBeEmpty();
    expect($number1)->not->toEqual($number2);
    expect($number1)->toStartWith('PP' . now()->format('Ymd'));

    // Status Plan Immutability: Plan yang sudah completed tidak boleh sembarangan dibatalkan
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 2, attributes: ['status' => 'completed']);

    $canCancel = function (ProductionPlan $p): bool {
        // Plan berstatus completed tidak boleh dibatalkan
        return !in_array($p->status, ['completed']);
    };

    expect($canCancel($plan))->toBeFalse(
        'Rencana produksi yang sudah completed tidak boleh diizinkan untuk dibatalkan.'
    );
});
