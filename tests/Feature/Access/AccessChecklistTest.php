<?php

/**
 * Peran & Hak Akses berbasis checklist (permintaan Ko Handy 27 Nov 2026):
 *  - menu & halaman mengikuti checklist peran; tanpa centang → menu hilang & server menolak (403)
 *  - peran duplikat (base_role) tetap jalan; akses per user bisa ditambah / dicabut
 *  - export / import Excel-CSV hanya Owner / Super Admin
 */

use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->ctx = stkContext();
    $this->user = $this->ctx['user'];
    $this->user->syncRoles([]);
    \App\Support\AccessDefaults::ensurePermissions();
    Access::flush();
});

function accessSetRole(Role $role, array $keys): void
{
    $role->syncPermissions([
        ...$role->permissions()->where('name', 'not like', Access::PREFIX . '%')->pluck('name')->all(),
        Access::CONFIGURED,
        ...array_map(fn ($k) => Access::PREFIX . $k, $keys),
    ]);
    Access::flush();
}

it('akses bawaan Sales = menu lama: SO tampil & bisa dibuka, Jurnal tidak tampil & ditolak', function () {
    $this->user->assignRole(Role::findByName('Sales'));

    $this->actingAs($this->user)->get(route('penjualan.so.index'))->assertOk()
        ->assertSee(route('penjualan.so.index'), false)
        ->assertDontSee(route('keuangan.journal.index'), false);

    $this->get(route('keuangan.journal.index'))->assertForbidden()
        ->assertSee('Pengaturan → Peran &amp; Hak Akses', false);
});

it('mencabut centang "Lihat" Pesanan Penjualan dari peran menghilangkan menu & menolak halaman', function () {
    $role = Role::findByName('Sales');
    $keys = array_values(array_filter(\App\Support\AccessDefaults::keysFor($role), fn ($k) => ! str_starts_with($k, 'penjualan.so.')));
    accessSetRole($role, $keys);
    $this->user->assignRole($role);

    $this->actingAs($this->user)->get(route('penjualan.so.index'))->assertForbidden();
    $this->get(route('penjualan.do.index'))->assertOk()
        ->assertDontSee('href="' . route('penjualan.so.index') . '" class="flex items-center', false)
        ->assertSee('href="' . route('penjualan.do.index') . '" class="flex items-center', false);
});

it('peran hasil duplikat (Sales → Sales 2) langsung bisa bekerja seperti peran asal', function () {
    $sales = Role::findByName('Sales');
    $clone = Role::create(['name' => 'Sales 2', 'guard_name' => 'web']);
    DB::table('roles')->where('id', $clone->id)->update(['base_role' => 'Sales']);
    accessSetRole($clone, \App\Support\AccessDefaults::keysFor($sales));
    $this->user->assignRole($clone);

    $this->actingAs($this->user)->get(route('penjualan.so.index'))->assertOk();
    $this->get(route('penjualan.so.create'))->assertOk();
    $this->get(route('keuangan.journal.index'))->assertForbidden();
});

it('akses khusus user: bisa ditambah & dicabut di luar peran', function () {
    $this->user->assignRole(Role::findByName('Sales'));
    DB::table('user_access_overrides')->insert([
        ['user_id' => $this->user->id, 'access_key' => 'keuangan.journal.view', 'allowed' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $this->user->id, 'access_key' => 'penjualan.so.view', 'allowed' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);
    Access::flush();

    $this->actingAs($this->user)->get(route('keuangan.journal.index'))->assertOk();
    $this->get(route('penjualan.so.index'))->assertForbidden();
});

it('export Excel hanya Owner / Super Admin, peran lain ditolak walau bisa melihat laporan', function () {
    $this->user->assignRole(Role::findByName('Sales'));
    expect(Access::keyForRoute('penjualan.laporan.excel'))->toBe('export');

    $this->actingAs($this->user)->get(route('penjualan.laporan.index'))->assertOk();
    $this->get(route('penjualan.laporan.excel'))->assertForbidden();

    $this->user->syncRoles([Role::findByName('Super Admin')]);
    Access::flush();
    $this->get(route('penjualan.laporan.excel'))->assertStatus(200);
});

it('Owner / Super Admin selalu penuh walau checklist perannya kosong', function () {
    $role = Role::findByName('Owner');
    accessSetRole($role, []);
    $this->user->assignRole($role);

    $this->actingAs($this->user)->get(route('keuangan.journal.index'))->assertOk();
    expect(Access::allows('gudang.transfer.void', $this->user))->toBeTrue();
});

it('pencarian bersama customer tidak diatur checklist (dipakai banyak form)', function () {
    expect(Access::keyForRoute('penjualan.customers.search'))->toBeNull()
        ->and(Access::keyForRoute('pembelian.products.search'))->toBeNull()
        ->and(Access::keyForRoute('penjualan.so.products.search'))->toBe('penjualan.so.view');
});

it('Data Master hanya untuk tingkat manajer: Sales & Purchasing ditolak, Manager & Finance Manager boleh', function () {
    foreach (['Sales', 'Purchasing', 'Accounting', 'Admin Keuangan', 'IT Support', 'Warehouse Staff'] as $roleName) {
        $this->user->syncRoles([Role::findByName($roleName)]);
        Access::flush();
        $this->actingAs($this->user)->get(route('master.customers.index'))->assertForbidden();
        $this->get(route('master.products.index'))->assertForbidden();
    }

    foreach (['Sales Manager' => 'master.customers.index', 'Purchasing Manager' => 'master.products.index', 'Finance Manager' => 'master.suppliers.index'] as $roleName => $route) {
        $this->user->syncRoles([Role::findByName($roleName)]);
        Access::flush();
        $this->actingAs($this->user)->get(route($route))->assertOk();
    }

    // Pencarian customer di form SO tetap jalan untuk Sales (bukan halaman Data Master)
    $this->user->syncRoles([Role::findByName('Sales')]);
    Access::flush();
    expect(Access::keyForRoute('penjualan.customers.search'))->toBeNull();
    $this->get(route('penjualan.so.create'))->assertOk();
});
