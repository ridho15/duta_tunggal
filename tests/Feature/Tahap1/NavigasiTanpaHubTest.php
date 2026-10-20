<?php

/**
 * Tahap 1 Spesifikasi UI/UX v1.1 (4.1) — tanpa halaman Hub, route lama dialihkan (301),
 * urutan grup tetap, dan label menu Bahasa Indonesia.
 */

use App\Models\Cabang;
use App\Models\User;
use App\Support\MenuAccess;

it('route Hub lama dialihkan permanen ke halaman tujuan', function (string $old, string $new) {
    $user = User::factory()->create(['cabang_id' => Cabang::query()->value('id')]);
    $user->assignRole('Owner');

    $this->actingAs($user)->get($old)->assertStatus(301)->assertRedirect($new);
})->with([
    ['/admin/sales-hub', '/admin/sale-orders'],
    ['/admin/purchase-hub', '/admin/purchase-orders'],
    ['/admin/delivery-hub', '/admin/delivery-orders'],
    ['/admin/inventory-hub', '/admin/inventory-stocks'],
    ['/admin/master-data-hub', '/admin/products'],
    ['/admin/finance-reports', '/admin/financial-statement'],
    ['/admin/dashboard-hub', '/admin'],
]);

it('sidebar Owner memakai urutan grup spesifikasi dan tidak memuat Hub', function () {
    $user = User::factory()->create(['cabang_id' => Cabang::query()->value('id')]);
    $user->assignRole('Owner');
    MenuAccess::flush();

    $html = $this->actingAs($user)->get('/admin')->assertOk()->getContent();

    preg_match_all('/<span[^>]*class="[^"]*fi-sidebar-group-label[^"]*"[^>]*>\s*(.*?)\s*<\/span>/s', $html, $groups);
    $groups = array_map(fn ($g) => trim(strip_tags($g)), $groups[1]);

    expect($groups)->toBe(['PENJUALAN', 'GUDANG', 'PEMBELIAN', 'KEUANGAN', 'LAPORAN', 'DATA MASTER', 'PENGATURAN'])
        ->and($html)->not->toContain('sales-hub')
        ->and($html)->not->toContain('master-data-hub')
        ->and($html)->toContain('Tugas Saya');
});

it('label tombol & kolom berbahasa Inggris diterjemahkan lewat lang/id.json', function () {
    app()->setLocale('id');

    expect(__('Approve'))->toBe('Setujui')
        ->and(__('Reject'))->toBe('Tolak')
        ->and(__('Created at'))->toBe('Dibuat pada')
        ->and(__('So number'))->toBe('No. SO')
        ->and(\Filament\Tables\Columns\TextColumn::make('created_at')->getLabel())->toBe('Dibuat pada')
        ->and(\Filament\Tables\Actions\Action::make('approve')->getLabel())->toBe('Setujui')
        ->and(\Filament\Forms\Components\TextInput::make('so_number')->getLabel())->toBe('No. SO');
});
