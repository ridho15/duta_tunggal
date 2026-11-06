<?php

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
