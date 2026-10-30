<?php

namespace Tests\Feature\Settings;

use App\Models\Cabang;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeUserWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $cashierUser;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Role $roleAdmin;
    protected Role $roleKasir;
    protected Role $roleGudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->roleKasir = Role::firstOrCreate(['name' => 'Kasir']);
        $this->roleGudang = Role::firstOrCreate(['name' => 'Warehouse Staff']);

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-USR-TEST',
            'nama' => 'Cabang Pengguna Test',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'name' => 'Gudang Pengguna Test',
                'cabang_id' => $this->cabang->id,
                'kode' => 'G-USR-01',
                'tipe' => 'utama',
                'status' => 'aktif',
            ]);

        $unique = uniqid();
        $this->adminUser = User::factory()->create([
            'email' => "super_admin_{$unique}@example.com",
            'username' => "sadmin_{$unique}",
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
            'status' => 1,
        ]);
        $this->adminUser->assignRole($this->roleAdmin);

        $this->cashierUser = User::factory()->create([
            'email' => "cashier_only_{$unique}@example.com",
            'username' => "cashier_{$unique}",
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['cabang'],
            'status' => 1,
        ]);
        $this->cashierUser->assignRole($this->roleKasir);
    }

    public function test_super_admin_can_access_users_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('settings.users.index'));

        $response->assertOk();
        $response->assertSee('Manajemen Pengguna & Hak Akses', false);
        $response->assertSee('Tambah Pengguna Baru');
        $response->assertSee($this->adminUser->name);
    }

    public function test_unauthorized_role_cannot_access_users_index(): void
    {
        $response = $this->actingAs($this->cashierUser)
            ->get(route('settings.users.index'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_view_create_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('settings.users.create'));

        $response->assertOk();
        $response->assertSee('Pendaftaran Pengguna Baru');
        $response->assertSee('Simpan Pengguna Baru');
    }

    public function test_can_store_new_user_with_roles_and_branch(): void
    {
        $unique = uniqid();
        $payload = [
            'first_name' => 'Ahmad',
            'last_name' => 'Dahlan',
            'username' => "ahmad_{$unique}",
            'email' => "ahmad_{$unique}@dutatunggal.co.id",
            'telepon' => '081234567890',
            'posisi' => 'Staff Kasir Ritel',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'roles' => ['Kasir'],
            'manage_type' => ['cabang'],
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 1,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('settings.users.store'), $payload);

        $response->assertRedirect(route('settings.users.index'));
        $response->assertSessionHas('success');

        $created = User::where('username', "ahmad_{$unique}")->first();
        $this->assertNotNull($created);
        $this->assertEquals('Ahmad Dahlan', $created->name);
        $this->assertTrue(Hash::check('secret123', $created->password));
        $this->assertTrue($created->hasRole('Kasir'));
        $this->assertStringStartsWith('USR-', $created->kode_user);
    }

    public function test_validation_error_when_username_or_email_already_taken(): void
    {
        $payload = [
            'first_name' => 'Duplikat',
            'last_name' => 'User',
            'username' => $this->adminUser->username, // Duplikat username
            'email' => $this->adminUser->email,       // Duplikat email
            'posisi' => 'Staff',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'roles' => ['Kasir'],
            'manage_type' => ['all'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('settings.users.store'), $payload);

        $response->assertSessionHasErrors(['username', 'email']);
    }

    public function test_validation_error_when_password_confirmation_mismatches(): void
    {
        $unique = uniqid();
        $payload = [
            'first_name' => 'Password',
            'last_name' => 'Mismatch',
            'username' => "pwd_{$unique}",
            'email' => "pwd_{$unique}@example.com",
            'posisi' => 'Staff',
            'password' => 'secret123',
            'password_confirmation' => 'wrongsecret',
            'roles' => ['Kasir'],
            'manage_type' => ['all'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('settings.users.store'), $payload);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_can_view_user_show_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('settings.users.show', $this->cashierUser));

        $response->assertOk();
        $response->assertSee('Detail Profil Pengguna');
        $response->assertSee($this->cashierUser->name);
        $response->assertSee('Kasir');
    }

    public function test_can_view_edit_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('settings.users.edit', $this->cashierUser));

        $response->assertOk();
        $response->assertSee($this->cashierUser->username);
        $response->assertSee('Simpan Perubahan');
    }

    public function test_can_update_user_and_change_roles(): void
    {
        $payload = [
            'first_name' => 'Kasir',
            'last_name' => 'Dinaikkan Pangkat',
            'username' => $this->cashierUser->username,
            'email' => $this->cashierUser->email,
            'telepon' => '08999999999',
            'posisi' => 'Kepala Gudang Baru',
            'roles' => ['Warehouse Staff'],
            'manage_type' => ['all'],
            'status' => 1,
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('settings.users.update', $this->cashierUser), $payload);

        $response->assertRedirect(route('settings.users.index'));
        $response->assertSessionHas('success');

        $this->cashierUser->refresh();
        $this->assertEquals('Kasir Dinaikkan Pangkat', $this->cashierUser->name);
        $this->assertEquals('Kepala Gudang Baru', $this->cashierUser->posisi);
        $this->assertTrue($this->cashierUser->hasRole('Warehouse Staff'));
        $this->assertFalse($this->cashierUser->hasRole('Kasir'));
    }

    public function test_can_toggle_active_status(): void
    {
        $this->assertEquals(1, $this->cashierUser->status);

        $response = $this->actingAs($this->adminUser)
            ->post(route('settings.users.toggle-active', $this->cashierUser));

        $response->assertSessionHas('success');
        $this->cashierUser->refresh();
        $this->assertEquals(0, $this->cashierUser->status);

        // Toggle back to active
        $this->actingAs($this->adminUser)
            ->post(route('settings.users.toggle-active', $this->cashierUser));

        $this->cashierUser->refresh();
        $this->assertEquals(1, $this->cashierUser->status);
    }

    public function test_cannot_toggle_own_active_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('settings.users.toggle-active', $this->adminUser));

        $response->assertSessionHas('error');
        $this->adminUser->refresh();
        $this->assertEquals(1, $this->adminUser->status);
    }

    public function test_can_reset_password(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('settings.users.reset-password', $this->cashierUser), [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertSessionHas('success');
        $this->cashierUser->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->cashierUser->password));
    }

    public function test_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->delete(route('settings.users.destroy', $this->adminUser));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->adminUser->id]);
    }

    public function test_can_delete_other_user(): void
    {
        $unique = uniqid();
        $tempUser = User::factory()->create([
            'email' => "temp_{$unique}@example.com",
            'username' => "temp_{$unique}",
            'status' => 1,
        ]);
        $tempUser->assignRole($this->roleKasir);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('settings.users.destroy', $tempUser));

        $response->assertRedirect(route('settings.users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $tempUser->id]);
    }

    public function test_filter_and_search_works(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('settings.users.index', ['search' => $this->cashierUser->username]));

        $response->assertOk();
        $response->assertSee($this->cashierUser->name);
    }
}
