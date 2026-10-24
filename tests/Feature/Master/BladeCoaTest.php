<?php

namespace Tests\Feature\Master;

use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeCoaTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_user_can_view_coa_index_page(): void
    {
        $code = '9999.' . rand(10, 99);
        ChartOfAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => 'Kas Uji Blade',
                'type' => 'Asset',
                'is_cash_bank' => true,
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($this->user)->get(route('master.coa.index'));

        $response->assertOk();
        $response->assertSee('Bagan Akun');
        $response->assertSee($code);
        $response->assertSee('Kas Uji Blade');
    }

    public function test_user_can_view_create_coa_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.coa.create'));

        $response->assertOk();
        $response->assertSee('Tambah Akun Baru');
    }

    public function test_user_can_store_new_coa(): void
    {
        $code = '1112.' . rand(100, 999);
        $payload = [
            'code' => $code,
            'name' => 'Bank Mandiri Uji',
            'type' => 'Asset',
            'is_cash_bank' => 1,
            'is_active' => 1,
            'opening_balance' => 5000000,
            'description' => 'Rekening uji coba',
        ];

        $response = $this->actingAs($this->user)->post(route('master.coa.store'), $payload);

        $response->assertRedirect(route('master.coa.index'));
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => $code,
            'name' => 'Bank Mandiri Uji',
            'is_cash_bank' => 1,
        ]);
    }

    public function test_user_can_edit_and_update_coa(): void
    {
        $code = '5100.' . rand(100, 999);
        $account = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Beban Uji',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $editPage = $this->actingAs($this->user)->get(route('master.coa.edit', $account));
        $editPage->assertOk();
        $editPage->assertSee('Beban Uji');

        $updateResponse = $this->actingAs($this->user)->put(route('master.coa.update', $account), [
            'code' => $code,
            'name' => 'Beban Uji Terupdate',
            'type' => 'Expense',
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect(route('master.coa.index'));
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => $code,
            'name' => 'Beban Uji Terupdate',
        ]);
    }

    public function test_user_can_delete_unused_coa(): void
    {
        $code = '9998.' . rand(100, 999);
        $account = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Akun Dihapus',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.coa.destroy', $account));

        $response->assertRedirect(route('master.coa.index'));
        $this->assertSoftDeleted('chart_of_accounts', [
            'id' => $account->id,
        ]);
    }
}
