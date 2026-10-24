<?php

namespace Tests\Feature\Master;

use App\Models\Currency;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeCurrencyTaxTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $roleOwner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole($roleOwner);

        $roleDriver = Role::firstOrCreate(['name' => 'Driver', 'guard_name' => 'web']);
        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole($roleDriver);
    }

    // ==================== AUTH & SECURITY TESTS ====================

    public function test_guest_cannot_access_currency_or_tax(): void
    {
        $this->get(route('master.mata-uang.index'))->assertRedirect(route('login'));
        $this->get(route('master.pajak.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_role_gets_forbidden(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('master.mata-uang.index'))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('master.pajak.index'))
            ->assertForbidden();
    }

    // ==================== MATA UANG (CURRENCY) TESTS ====================

    public function test_user_can_view_currency_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.mata-uang.index'));

        $response->assertOk();
        $response->assertSee('Daftar Mata Uang');
        $response->assertSee('Tambah Mata Uang');
    }

    public function test_user_can_view_create_currency_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.mata-uang.create'));

        $response->assertOk();
        $response->assertSee('Tambah Mata Uang Baru');
        $response->assertSee('Kode ISO Valuta');
    }

    public function test_user_can_store_new_currency(): void
    {
        $code = 'TS' . rand(10, 99);
        $payload = [
            'name' => 'Test Currency ' . $code,
            'code' => $code,
            'symbol' => 'T$',
            'to_rupiah' => 15500.50,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('master.mata-uang.store'), $payload);

        $response->assertRedirect(route('master.mata-uang.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('currencies', [
            'code' => $code,
            'symbol' => 'T$',
        ]);
    }

    public function test_user_can_view_edit_currency_page(): void
    {
        $currency = Currency::create([
            'name' => 'Edit Currency Test',
            'code' => 'EC' . rand(10, 99),
            'symbol' => 'E$',
            'to_rupiah' => 12000.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.mata-uang.edit', $currency));

        $response->assertOk();
        $response->assertSee('Edit Mata Uang');
        $response->assertSee($currency->code);
    }

    public function test_user_can_update_currency(): void
    {
        $currency = Currency::create([
            'name' => 'Update Currency Test',
            'code' => 'UC' . rand(10, 99),
            'symbol' => 'U$',
            'to_rupiah' => 10000.00,
        ]);

        $payload = [
            'name' => 'Updated Currency Name',
            'code' => $currency->code,
            'symbol' => 'U$$',
            'to_rupiah' => 10500.00,
        ];

        $response = $this->actingAs($this->user)
            ->put(route('master.mata-uang.update', $currency), $payload);

        $response->assertRedirect(route('master.mata-uang.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'name' => 'Updated Currency Name',
            'symbol' => 'U$$',
        ]);
    }

    public function test_base_currency_idr_cannot_be_deleted(): void
    {
        $idr = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1.00]
        );

        $response = $this->actingAs($this->user)
            ->delete(route('master.mata-uang.destroy', $idr));

        $response->assertRedirect(route('master.mata-uang.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('currencies', [
            'id' => $idr->id,
            'deleted_at' => null,
        ]);
    }

    public function test_unbound_currency_can_be_deleted(): void
    {
        $currency = Currency::create([
            'name' => 'Delete Currency Test',
            'code' => 'DC' . rand(10, 99),
            'symbol' => 'D$',
            'to_rupiah' => 8000.00,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('master.mata-uang.destroy', $currency));

        $response->assertRedirect(route('master.mata-uang.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('currencies', [
            'id' => $currency->id,
        ]);
    }

    // ==================== PENGATURAN PAJAK (TAX SETTING) TESTS ====================

    public function test_user_can_view_tax_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.pajak.index'));

        $response->assertOk();
        $response->assertSee('Daftar Pengaturan Pajak');
        $response->assertSee('Tambah Tarif Pajak');
    }

    public function test_user_can_view_create_tax_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.pajak.create'));

        $response->assertOk();
        $response->assertSee('Tambah Tarif Pajak Baru');
        $response->assertSee('Nama Pengaturan Pajak');
    }

    public function test_user_can_store_tax_setting(): void
    {
        $payload = [
            'name' => 'PPN Test ' . rand(100, 999),
            'type' => 'PPN',
            'rate' => 12.00,
            'effective_date' => date('Y-m-d'),
            'status' => '1',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('master.pajak.store'), $payload);

        $response->assertRedirect(route('master.pajak.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tax_settings', [
            'name' => $payload['name'],
            'type' => 'PPN',
            'rate' => 12.00,
        ]);
    }

    public function test_user_can_view_edit_tax_page(): void
    {
        $tax = TaxSetting::create([
            'name' => 'Edit Tax Test',
            'type' => 'Custom',
            'rate' => 5.00,
            'effective_date' => date('Y-m-d'),
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.pajak.edit', $tax));

        $response->assertOk();
        $response->assertSee('Edit Tarif Pajak');
        $response->assertSee('Edit Tax Test');
    }

    public function test_user_can_update_tax_setting(): void
    {
        $tax = TaxSetting::create([
            'name' => 'Original Tax Test',
            'type' => 'Custom',
            'rate' => 2.50,
            'effective_date' => date('Y-m-d'),
            'status' => true,
        ]);

        $payload = [
            'name' => 'Updated Tax Test',
            'type' => 'Custom',
            'rate' => 3.50,
            'effective_date' => date('Y-m-d'),
            'status' => '0',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('master.pajak.update', $tax), $payload);

        $response->assertRedirect(route('master.pajak.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tax_settings', [
            'id' => $tax->id,
            'name' => 'Updated Tax Test',
            'rate' => 3.50,
            'status' => 0,
        ]);
    }

    public function test_standalone_tax_setting_can_be_deleted(): void
    {
        $tax = TaxSetting::create([
            'name' => 'Delete Tax Test',
            'type' => 'Custom',
            'rate' => 1.50,
            'effective_date' => date('Y-m-d'),
            'status' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('master.pajak.destroy', $tax));

        $response->assertRedirect(route('master.pajak.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('tax_settings', [
            'id' => $tax->id,
        ]);
    }
}
