<?php

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Fixture context untuk pengujian modul Aset Tetap & Penyusutan (Tahap 6).
 */
function astContext(array $overrides = []): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'AST-' . strtoupper(substr(uniqid(), -5)),
        'nama' => 'Cabang Pengelolaan Aset Tetap',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'username' => 'ast_' . uniqid(),
        'email' => 'ast_' . uniqid() . '@example.com',
        'kode_user' => 'U' . strtoupper(substr(uniqid(), -4)),
        'manage_type' => 'all',
    ]);
    Auth::login($user);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]
    );

    $assetCoa = $coa('1210.01', 'Kendaraan Operasional Perusahaan', 'Asset');
    $accumulatedDepreciationCoa = $coa('1220.01', 'Akumulasi Penyusutan Kendaraan', 'Asset');
    $depreciationExpenseCoa = $coa('6311.01', 'Beban Penyusutan Kendaraan', 'Expense');
    $kasCoa = $coa('1101', 'Kas Utama Perusahaan', 'Asset');
    $apCoa = $coa('2100', 'Hutang Usaha Pengadaan Aset', 'Liability');
    $gainCoa = $coa('4100.99', 'Keuntungan Pelepasan Aset Tetap', 'Revenue');
    $lossCoa = $coa('5200.99', 'Kerugian Pelepasan Aset Tetap', 'Expense');

    return array_merge(compact(
        'cabang', 'user', 'assetCoa', 'accumulatedDepreciationCoa',
        'depreciationExpenseCoa', 'kasCoa', 'apCoa', 'gainCoa', 'lossCoa'
    ), $overrides);
}

/**
 * Buat Asset dengan nilai perolehan dan masa manfaat default.
 */
function astCreate(array $ctx, array $attributes = []): Asset
{
    $requestedStatus = $attributes['status'] ?? 'active';

    $asset = Asset::create(array_merge([
        'name' => 'Mobil Pick-Up Operasional ' . strtoupper(substr(uniqid(), -4)),
        'purchase_date' => now()->subMonths(12)->toDateString(),
        'usage_date' => now()->subMonths(12)->toDateString(),
        'purchase_cost' => 120000000.0, // 120 Juta
        'salvage_value' => 12000000.0,  // 12 Juta (Nilai Residu 10%)
        'useful_life_years' => 5,       // 5 Tahun = 60 Bulan
        'depreciation_method' => 'straight_line',
        'asset_coa_id' => $ctx['assetCoa']->id,
        'accumulated_depreciation_coa_id' => $ctx['accumulatedDepreciationCoa']->id,
        'depreciation_expense_coa_id' => $ctx['depreciationExpenseCoa']->id,
        'status' => $requestedStatus,
        'cabang_id' => $ctx['cabang']->id,
    ], $attributes));

    if ($asset->status !== $requestedStatus) {
        $asset->update(['status' => $requestedStatus]);
    }

    return $asset->fresh();
}
