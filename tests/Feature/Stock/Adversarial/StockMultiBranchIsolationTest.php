<?php

use App\Filament\Resources\AccountReceivableResource;
use App\Models\Cabang;
use App\Models\Warehouse;
use App\Services\StockAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => [
        'ledger' => false,
        'strict_dispatch' => true,
        'reserve_on_so_approve' => false,
        'block_short_approval' => false,
    ]]);
});

it('SC-01: isolasi cabang membatasi gudang yang dapat diakses jika lihat_stok_cabang_lain bernilai false', function () {
    $ctx = stkContext();

    // Cabang A (default tidak boleh lihat cabang lain)
    $cabangA = $ctx['cabang'];
    $cabangA->update(['lihat_stok_cabang_lain' => false]);
    $gudangA = $ctx['warehouse']; // milik Cabang A

    // Cabang B dan Gudang B
    $cabangB = Cabang::factory()->create(['nama' => 'Cabang B', 'lihat_stok_cabang_lain' => false]);
    $gudangB = Warehouse::factory()->create(['cabang_id' => $cabangB->id, 'name' => 'Gudang Cabang B', 'status' => 1]);

    $availability = app(StockAvailability::class);

    // Untuk Cabang A: hanya Gudang A yang boleh diakses
    $accessibleA = $availability->accessibleWarehouseIds($cabangA->id);
    expect($accessibleA)->toContain($gudangA->id)
        ->and($accessibleA)->not->toContain($gudangB->id);

    // Bila flag lihat_stok_cabang_lain diaktifkan
    $cabangA->update(['lihat_stok_cabang_lain' => true]);
    $accessibleAll = $availability->accessibleWarehouseIds($cabangA->id);
    expect($accessibleAll)->toContain($gudangA->id)
        ->and($accessibleAll)->toContain($gudangB->id);
});

it('SC-02: proteksi sub-ledger piutang menolak pembuatan atau modifikasi manual langsung', function () {
    // Verifikasi bahwa canCreate, canEdit, dan canDelete bernilai false
    expect(AccountReceivableResource::canCreate())->toBeFalse()
        ->and(AccountReceivableResource::canEdit(new \App\Models\AccountReceivable()))->toBeFalse()
        ->and(AccountReceivableResource::canDelete(new \App\Models\AccountReceivable()))->toBeFalse();
});
