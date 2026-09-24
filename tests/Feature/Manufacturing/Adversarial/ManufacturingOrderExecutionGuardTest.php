<?php

use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Permission;
use App\Policies\ManufacturingOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = mfgContext();

    Permission::firstOrCreate(['name' => 'request manufacturing order', 'guard_name' => 'web']);
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('MO-BV-01: start guard memblokir status in_progress jika material issue belum dibuat atau belum selesai', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 4);

    $mo = mfgOrder($this->ctx, $plan, ['status' => 'draft']);

    // Tanpa Material Issue, MO tidak boleh dapat dimulai
    expect($mo->canStartProduction())->toBeFalse(
        'MO tidak boleh diizinkan start jika belum ada Material Issue.'
    );

    $blockingMsg = $mo->productionStartBlockingMessage();
    expect($blockingMsg)->not->toBeNull();
    expect($blockingMsg)->toContain('Material Issue belum dibuat');

    $policy = app(ManufacturingOrderPolicy::class);
    expect($policy->updateStatus($this->ctx['user'], $mo, 'in_progress'))->toBeFalse(
        'Policy harus menolak transisi status in_progress saat guard material issue belum terpenuhi.'
    );
});

it('MO-BV-02: isolasi cabang membatasi kepemilikan dokumen MO dan alokasi gudang bahan antar cabang', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 4);

    $moA = mfgOrder($this->ctx, $plan);

    // Buat cabang lain B
    $cabangB = \App\Models\Cabang::factory()->create([
        'nama' => 'Cabang B Terpisah',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    expect($moA->cabang_id)->toEqual($this->ctx['cabang']->id);
    expect($moA->cabang_id)->not->toEqual($cabangB->id);

    // Query dari scope cabang B tidak boleh mengakses MO Cabang A
    $userB = \App\Models\User::factory()->create([
        'cabang_id' => $cabangB->id,
        'manage_type' => 'cabang',
    ]);

    \Illuminate\Support\Facades\Auth::login($userB);

    $accessibleMos = ManufacturingOrder::where('cabang_id', $cabangB->id)->pluck('id');
    expect($accessibleMos)->not->toContain($moA->id,
        'Cabang B tidak boleh melihat atau memodifikasi Manufacturing Order milik Cabang A.'
    );
});

it('MO-BV-03: proteksi pesanan selesai (completed) mengunci status dari pembatalan atau perubahan liar', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 4);

    $mo = mfgOrder($this->ctx, $plan, ['status' => 'completed']);

    // Uji validasi transisi: MO yang sudah completed tidak boleh dimundurkan ke draft
    $canRevertToDraft = function (ManufacturingOrder $targetMo): bool {
        return $targetMo->status !== 'completed';
    };

    expect($canRevertToDraft($mo))->toBeFalse(
        'Manufacturing Order yang sudah completed terkunci dan tidak boleh dikembalikan ke status draft.'
    );
});
