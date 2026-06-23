<?php

use App\Filament\Resources\AssetDisposalResource\Pages\CreateAssetDisposal;
use App\Filament\Resources\AssetTransferResource\Pages\CreateAssetTransfer;
use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any asset',
        'view asset',
        'create asset transfer',
        'view any asset transfer',
        'create asset disposal',
        'view any asset disposal',
        'view any cabang',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $this->user = User::factory()->create([
        'manage_type' => 'all',
    ]);

    $this->user->givePermissionTo([
        'view any asset',
        'view asset',
        'create asset transfer',
        'view any asset transfer',
        'create asset disposal',
        'view any asset disposal',
        'view any cabang',
    ]);
});

test('asset transfer validation uses the selected branch state', function () {
    $sourceCabang = Cabang::factory()->create();
    $targetCabang = Cabang::factory()->create();
    $this->user->update(['cabang_id' => $sourceCabang->id]);

    $asset = Asset::factory()->create([
        'cabang_id' => $sourceCabang->id,
        'status' => 'active',
        'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'Asset'])->id,
        'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'Contra Asset'])->id,
        'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'Expense'])->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(CreateAssetTransfer::class)
        ->assertSuccessful()
        ->fillForm([
            'asset_id' => $asset->id,
            'from_cabang_id' => $sourceCabang->id,
            'to_cabang_id' => $sourceCabang->id,
            'transfer_date' => now()->toDateString(),
            'reason' => 'Relokasi aset antar cabang',
        ])
        ->call('create')
        ->assertHasFormErrors(['to_cabang_id']);
});

test('asset disposal validation requires sale price for sale disposals', function () {
    $cabang = Cabang::factory()->create();
    $this->user->update(['cabang_id' => $cabang->id]);

    $asset = Asset::factory()->create([
        'cabang_id' => $cabang->id,
        'status' => 'active',
        'purchase_cost' => 1000000,
        'accumulated_depreciation' => 200000,
        'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'Asset'])->id,
        'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'Contra Asset'])->id,
        'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'Expense'])->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(CreateAssetDisposal::class)
        ->assertSuccessful()
        ->fillForm([
            'asset_id' => $asset->id,
            'disposal_date' => now()->toDateString(),
            'disposal_type' => 'sale',
            'notes' => 'Penjualan aset bekas',
        ])
        ->call('create')
        ->assertHasFormErrors(['sale_price']);
});