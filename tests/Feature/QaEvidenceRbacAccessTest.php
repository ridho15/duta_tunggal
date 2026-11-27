<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cabang;
use App\Support\Access;
use App\Support\AccessDefaults;
use App\Support\AccessManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

class QaEvidenceRbacAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected User $salesManager;
    protected User $sales;
    protected User $purchasing;
    protected Cabang $cabang;

    protected function shouldSeedBaseData(): bool
    {
        return ! (Cabang::exists() && Role::where('name', 'Owner')->exists());
    }

    protected function setUp(): void
    {
        parent::setUp();

        AccessDefaults::ensurePermissions();
        AccessDefaults::installAll();
        Access::flush();

        $this->cabang = Cabang::firstOrFail();

        $this->owner = User::role('Owner')->first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (! $this->owner->hasRole('Owner')) {
            $this->owner->assignRole('Owner');
        }

        $this->salesManager = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->salesManager->assignRole('Sales Manager');

        $this->sales = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->sales->assignRole('Sales');

        $this->purchasing = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->purchasing->assignRole('Purchasing');
    }

    /**
     * REQ 1: Owner & Super Admin selalu penuh & berhak mengatur Peran & Hak Akses
     */
    public function test_owner_has_full_access_to_roles_and_permissions(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('settings.roles.index'));
        $response->assertOk();
        $response->assertSee('Peran');
        $response->assertSee('Owner');
        $response->assertSee('Sales');

        $salesRole = Role::findByName('Sales');
        $editResponse = $this->get(route('settings.roles.edit', $salesRole));
        $editResponse->assertOk();
        $editResponse->assertSee('Penjualan');
    }

    /**
     * REQ 2: Data Master KHUSUS tingkat manajer (Keputusan Ko Handy 27 Nov 2026)
     * Sales & Purchasing DITOLAK (403), Sales Manager (Customer) & Inventory Manager (Produk) & Owner DIPERBOLEHKAN (200).
     */
    public function test_master_data_restricted_to_manager_levels(): void
    {
        $admin = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $admin->assignRole('Admin');

        // 1. Sales mencoba buka Master Produk & Master Customer -> Ditolak (403)
        $this->actingAs($this->sales);
        $this->assertEquals(403, $this->get(route('master.products.index'))->getStatusCode(), 'Sales harus ditolak dari Master Produk');
        $this->assertEquals(403, $this->get(route('master.customers.index'))->getStatusCode(), 'Sales harus ditolak dari Master Customer');

        // 2. Purchasing mencoba buka Master Produk -> Ditolak (403)
        $this->actingAs($this->purchasing);
        $this->assertEquals(403, $this->get(route('master.products.index'))->getStatusCode(), 'Purchasing harus ditolak dari Master Produk');

        // 3. Sales Manager membuka Master Customer -> Lolos (200)
        $this->actingAs($this->salesManager);
        $this->assertEquals(200, $this->get(route('master.customers.index'))->getStatusCode(), 'Sales Manager harus diizinkan membuka Master Customer');

        // 4. Admin (tingkat manajer) membuka Master Produk -> Lolos (200)
        $this->actingAs($admin);
        $this->assertEquals(200, $this->get(route('master.products.index'))->getStatusCode(), 'Admin harus diizinkan membuka Master Produk');

        // 5. Owner membuka Master Produk -> Lolos (200)
        $this->actingAs($this->owner);
        $this->assertEquals(200, $this->get(route('master.products.index'))->getStatusCode(), 'Owner harus diizinkan membuka Master Produk');
    }

    /**
     * REQ 3: Non-Owner dilarang mengakses & mengubah pengaturan peran
     */
    public function test_non_owner_cannot_access_or_manage_roles(): void
    {
        $this->actingAs($this->sales);
        $this->get(route('settings.roles.index'))->assertForbidden();

        $this->actingAs($this->salesManager);
        $this->get(route('settings.roles.index'))->assertForbidden();
    }

    /**
     * REQ 4: Duplikasi peran membawa hak akses template / base_role
     */
    public function test_role_duplication_and_template_cloning(): void
    {
        $this->actingAs($this->owner);

        $salesRole = Role::findByName('Sales');
        $cloneName = 'Sales QA Clone ' . uniqid();

        $response = $this->post(route('settings.roles.duplicate', $salesRole), [
            'name' => $cloneName,
        ]);
        $response->assertRedirect();

        $clonedRole = Role::where('name', $cloneName)->first();
        $this->assertNotNull($clonedRole, 'Peran hasil kloning harus terdaftar di database');
        $this->assertEquals('Sales', $clonedRole->base_role, 'Base role harus tercatat sebagai Sales');

        $userClone = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $userClone->assignRole($clonedRole);
        Access::flush();

        $this->actingAs($userClone);
        $this->get(route('penjualan.so.index'))->assertOk();
        $this->get(route('master.products.index'))->assertForbidden();
    }

    /**
     * REQ 5: User Access Override (Hak Akses Khusus User: tambah & cabut)
     */
    public function test_user_specific_access_override_grant_and_revoke(): void
    {
        $this->actingAs($this->owner);

        // Beri Sales hak khusus membuka Jurnal Keuangan via override
        $this->put(route('settings.users.access.update', $this->sales), [
            'add' => ['keuangan.journal.view'],
            'revoke' => [],
        ])->assertRedirect();

        Access::flush();
        $this->actingAs($this->sales);
        $this->get(route('keuangan.journal.index'))->assertOk();

        // Reset override ke bawaan peran
        $this->actingAs($this->owner);
        $this->post(route('settings.users.access.reset', $this->sales))->assertRedirect();

        Access::flush();
        $this->actingAs($this->sales);
        $this->get(route('keuangan.journal.index'))->assertForbidden();
    }
}
