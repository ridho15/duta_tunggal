<?php

/**
 * Tahap 1 Spesifikasi UI/UX v1.1 — "Setiap peran login hanya melihat menunya (5.2)".
 * Menu dan URL sama-sama terkunci (Policy + config/menu_access.php).
 */

use App\Filament\Pages\ModulAktifPage;
use App\Filament\Resources\BillOfMaterialResource;
use App\Filament\Resources\ChartOfAccountResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\SaleOrderResource;
use App\Filament\Resources\UserResource;
use App\Models\Cabang;
use App\Models\User;
use App\Support\MenuAccess;

function tahap1User(string $role): User
{
    $user = User::factory()->create([
        'cabang_id' => Cabang::query()->value('id'),
    ]);
    $user->assignRole($role);
    MenuAccess::flush();

    return $user;
}

/** Label menu di sidebar halaman Beranda. */
function tahap1SidebarLabels(User $user): array
{
    test()->actingAs($user);
    $html = test()->get('/admin')->assertOk()->getContent();

    preg_match_all('/<span[^>]*class="[^"]*fi-sidebar-item-label[^"]*"[^>]*>\s*(.*?)\s*<\/span>/s', $html, $m);

    return array_map(fn ($label) => trim(html_entity_decode(strip_tags($label))), $m[1]);
}

it('memetakan role ke kode peran spesifikasi', function () {
    expect(MenuAccess::codesFor(tahap1User('Kasir')))->toBe(['KSR'])
        ->and(MenuAccess::codesFor(tahap1User('Admin Pengiriman')))->toBe(['ADP'])
        ->and(MenuAccess::codesFor(tahap1User('Checker')))->toBe(['GDG'])
        ->and(MenuAccess::codesFor(tahap1User('Owner')))->toBe(['OWN']);
});

it('kasir hanya melihat menu kasir', function () {
    $labels = tahap1SidebarLabels(tahap1User('Kasir'));

    expect($labels)->toContain('Tugas Saya')
        ->toContain('Pesanan Penjualan (SO)')
        ->not->toContain('Jurnal')
        ->not->toContain('Pesanan Pembelian (PO)')
        ->not->toContain('Pengguna & Hak Akses')
        ->not->toContain('Akun & Pajak')
        ->not->toContain('Dashboard Owner');
});

it('checker gudang tidak melihat menu penjualan dan keuangan', function () {
    $labels = tahap1SidebarLabels(tahap1User('Checker'));

    expect($labels)->toContain('Siapkan & Kirim')
        ->not->toContain('Pesanan Penjualan (SO)')
        ->not->toContain('Invoice Penjualan')
        ->not->toContain('Kas & Bank')
        ->not->toContain('Jurnal');
});

it('owner melihat semua grup utama', function () {
    $labels = tahap1SidebarLabels(tahap1User('Owner'));

    expect($labels)->toContain('Pesanan Penjualan (SO)')
        ->toContain('Pesanan Pembelian (PO)')
        ->toContain('Jurnal')
        ->toContain('Pengguna & Hak Akses')
        ->toContain('Modul Aktif');
});

it('URL menu di luar peran ditolak 403 walau diketik langsung', function () {
    $this->actingAs(tahap1User('Kasir'));
    $this->get(JournalEntryResource::getUrl('index'))->assertForbidden();
    $this->get(PurchaseOrderResource::getUrl('index'))->assertForbidden();
    $this->get(ChartOfAccountResource::getUrl('index'))->assertForbidden();
    $this->get(UserResource::getUrl('index'))->assertForbidden();
    $this->get(ModulAktifPage::getUrl())->assertForbidden();
});

it('URL menu di dalam peran tetap bisa dibuka', function () {
    $this->actingAs(tahap1User('Kasir'));
    $this->get(SaleOrderResource::getUrl('index'))->assertOk();

    $this->actingAs(tahap1User('Admin Pengiriman'));
    $this->get(DeliveryOrderResource::getUrl('index'))->assertOk();
});

it('menu modul produksi tersembunyi sampai modulnya diaktifkan', function () {
    $this->actingAs(tahap1User('Owner'));

    expect(BillOfMaterialResource::shouldRegisterNavigation())->toBeFalse();

    MenuAccess::setModule('manufacturing', true);

    expect(BillOfMaterialResource::shouldRegisterNavigation())->toBeTrue();
});

it('setiap peran melihat semua menu di matriksnya setelah izin lihat diberikan', function (string $role, array $mustSee) {
    $labels = tahap1SidebarLabels(tahap1User($role));

    foreach ($mustSee as $label) {
        expect($labels)->toContain($label);
    }
})->with([
    'Kasir' => ['Kasir', ['Cek Stok', 'Customer', 'Perintah Kirim (DO)', 'Piutang & Hutang']],
    'Checker' => ['Checker', ['Terima Barang', 'Pindah Stok', 'Permintaan Pembelian', 'Retur Pembelian', 'Retur DO']],
    'Sales' => ['Sales', ['Cek Stok', 'Perintah Kirim (DO)', 'Permintaan Pembelian', 'Retur DO']],
    'Purchasing' => ['Purchasing', ['Cek Stok', 'Invoice Pembelian', 'Pengajuan Dana']],
    'Accounting' => ['Accounting', ['Bayar Supplier', 'Customer', 'Supplier', 'Dashboard Owner', 'Terima Pembayaran']],
]);
