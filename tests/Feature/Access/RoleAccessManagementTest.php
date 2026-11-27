<?php

/**
 * Halaman Peran & Hak Akses + Akses User (Ko Handy 27 Nov 2026): hanya Owner / Super Admin,
 * duplikat peran, tambah peran dari template, akses khusus user, salin dari user lain, riwayat.
 */

use App\Models\User;
use App\Support\Access;
use App\Support\AccessDefaults;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->ctx = stkContext();
    AccessDefaults::ensurePermissions();
    $this->owner = $this->ctx['user'];
    $this->owner->syncRoles([Role::findByName('Owner')]);
    $this->staff = User::factory()->create(['cabang_id' => $this->owner->cabang_id, 'manage_type' => ['all']]);
    $this->staff->assignRole('Sales');
    Access::flush();
});

it('hanya Owner / Super Admin yang bisa membuka & menyimpan peran', function () {
    $it = User::factory()->create(['cabang_id' => $this->owner->cabang_id]);
    $it->assignRole('IT Support');

    $this->actingAs($it)->get(route('settings.roles.index'))->assertForbidden();
    $this->put(route('settings.roles.update', Role::findByName('Sales')), ['name' => 'Sales', 'keys' => []])->assertForbidden();
    $this->get(route('settings.users.access.edit', $this->staff))->assertForbidden();

    $this->actingAs($this->owner)->get(route('settings.roles.index'))->assertOk()->assertSee('Sales');
    $this->get(route('settings.roles.edit', Role::findByName('Sales')))->assertOk()->assertSee('Pesanan Penjualan');
});

it('duplikat peran: Sales → Sales 2 membawa centang & bisa diubah tanpa mengubah peran asal', function () {
    $sales = Role::findByName('Sales');
    $this->actingAs($this->owner)->post(route('settings.roles.duplicate', $sales), ['name' => 'Sales 2'])
        ->assertRedirect();

    $copy = Role::findByName('Sales 2');
    expect($copy->base_role)->toBe('Sales')
        ->and(\App\Support\AccessManager::roleKeys($copy))->toEqualCanonicalizing(AccessDefaults::keysFor($sales));

    $keys = array_values(array_diff(\App\Support\AccessManager::roleKeys($copy), ['penjualan.so.create']));
    $this->put(route('settings.roles.update', $copy), ['name' => 'Sales Cabang', 'keys' => $keys])->assertRedirect();

    $copy->refresh();
    expect($copy->name)->toBe('Sales Cabang')
        ->and(\App\Support\AccessManager::roleKeys($copy))->not->toContain('penjualan.so.create')
        ->and(\App\Support\AccessManager::roleKeys($sales))->toContain('penjualan.so.create');

    $u = User::factory()->create(['cabang_id' => $this->owner->cabang_id, 'manage_type' => ['all']]);
    $u->assignRole($copy);
    Access::flush();
    $this->actingAs($u)->get(route('penjualan.so.index'))->assertOk();
    $this->get(route('penjualan.so.create'))->assertForbidden();

    expect(DB::table('access_change_logs')->where('target_type', 'role')->where('target_id', $copy->id)->count())->toBe(2);
});

it('nama peran bawaan terkunci, peran super tidak bisa dikurangi, peran dipakai tidak bisa dihapus', function () {
    $sales = Role::findByName('Sales');
    $this->actingAs($this->owner)->put(route('settings.roles.update', $sales), ['name' => 'Penjual', 'keys' => ['penjualan.so.view']])->assertRedirect();
    expect($sales->fresh()->name)->toBe('Sales');

    $this->put(route('settings.roles.update', Role::findByName('Owner')), ['name' => 'Owner', 'keys' => []])
        ->assertSessionHas('error');

    $this->delete(route('settings.roles.destroy', $sales))->assertSessionHas('error');
    expect(Role::where('name', 'Sales')->exists())->toBeTrue();

    $tmp = Role::create(['name' => 'Sementara', 'guard_name' => 'web']);
    $this->delete(route('settings.roles.destroy', $tmp))->assertRedirect(route('settings.roles.index'));
    expect(Role::where('name', 'Sementara')->exists())->toBeFalse();
});

it('tambah peran dari template mengisi centang template', function () {
    $tpl = Role::findByName('Accounting');
    $this->actingAs($this->owner)->get(route('settings.roles.create', ['template' => $tpl->id]))->assertOk()->assertSee('Accounting');

    $this->post(route('settings.roles.store'), [
        'name' => 'Akuntan 2', 'template_id' => $tpl->id, 'keys' => ['keuangan.journal.view'],
    ])->assertRedirect();

    $role = Role::findByName('Akuntan 2');
    expect($role->base_role)->toBe('Accounting')
        ->and(\App\Support\AccessManager::roleKeys($role))->toBe(['keuangan.journal.view']);

    $this->post(route('settings.roles.store'), ['name' => 'Akuntan 2'])->assertSessionHasErrors('name');
});

it('akses khusus user: tambah & cabut tersimpan, kunci sama dengan peran dibuang, bisa dikembalikan', function () {
    $this->actingAs($this->owner)->get(route('settings.users.access.edit', $this->staff))->assertOk()->assertSee('Akses '.$this->staff->name);

    $this->put(route('settings.users.access.update', $this->staff), [
        'add' => ['keuangan.journal.view', 'penjualan.so.view'],   // so.view sudah dari peran → dibuang
        'revoke' => ['penjualan.so.create', 'keuangan.period.view'], // period.view bukan dari peran → dibuang
    ])->assertRedirect();

    expect(Access::overrides($this->staff))->toBe(['keuangan.journal.view' => true, 'penjualan.so.create' => false]);
    expect(Access::allows('keuangan.journal.view', $this->staff))->toBeTrue()
        ->and(Access::allows('penjualan.so.create', $this->staff))->toBeFalse();

    $this->post(route('settings.users.access.reset', $this->staff))->assertRedirect();
    expect(Access::overrides($this->staff))->toBe([]);
});

it('salin peran & akses dari user lain', function () {
    $source = User::factory()->create(['cabang_id' => $this->owner->cabang_id]);
    $source->assignRole('Accounting');
    DB::table('user_access_overrides')->insert(['user_id' => $source->id, 'access_key' => 'keuangan.journal.view', 'allowed' => 0, 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($this->owner)->post(route('settings.users.access.copy', $this->staff), ['source_user_id' => $source->id])
        ->assertRedirect();

    $staff = $this->staff->fresh();
    expect($staff->getRoleNames()->all())->toBe(['Accounting'])
        ->and(Access::overrides($staff))->toBe(['keuangan.journal.view' => false]);
    expect(DB::table('access_change_logs')->where('target_type', 'user')->where('action', 'copy_from_user')->exists())->toBeTrue();
});

it('non Owner tidak bisa mengganti peran user lewat form pengguna', function () {
    $it = User::factory()->create(['cabang_id' => $this->owner->cabang_id]);
    $it->assignRole('IT Support');
    Access::flush();

    $this->actingAs($it)->get(route('settings.users.edit', $this->staff))->assertOk()
        ->assertSee('Hanya Owner / Super Admin yang bisa memberi atau mengganti peran');
});
