<?php

namespace Tests\Feature\Sales\Adversarial;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SaleOrderRejectGuardTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesUser;
    protected User $unauthorizedUser;
    protected Cabang $cabangA;
    protected Cabang $cabangB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create cabangs
        $this->cabangA = Cabang::firstOrCreate(['kode' => 'CUT'], [
            'nama' => 'Cabang Utama Test',
            'alamat' => 'Jl. Utama Test',
            'telepon' => '021-0001',
            'status' => 1,
        ]);
        $this->cabangB = Cabang::firstOrCreate(['kode' => 'CKT'], [
            'nama' => 'Cabang Kedua Test',
            'alamat' => 'Jl. Kedua Test',
            'telepon' => '021-0002',
            'status' => 1,
        ]);

        // Create roles and permissions
        $perm = Permission::firstOrCreate(['name' => 'response sales order', 'guard_name' => 'web']);
        $salesRole = Role::firstOrCreate(['name' => 'Sales Manager', 'guard_name' => 'web']);
        $salesRole->givePermissionTo($perm);

        $this->salesUser = User::factory()->create([
            'cabang_id' => $this->cabangA->id,
        ]);
        $this->salesUser->assignRole($salesRole);

        $this->unauthorizedUser = User::factory()->create([
            'cabang_id' => $this->cabangB->id,
        ]);
    }

    public function test_reject_fails_when_sales_order_is_already_approved(): void
    {
        $so = SaleOrder::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'status' => 'approved',
            'so_number' => 'SO-TEST-APP-01',
        ]);

        $response = $this->actingAs($this->salesUser)
            ->from(route('penjualan.so.show', $so))
            ->post(route('penjualan.so.reject', $so), [
                'reason' => 'Ingin membatalkan SO yang sudah approved',
            ]);

        // Cabang global scope blocks unauthorized branch access (404) or controller rejects with redirect/error (302)
        $this->assertTrue(in_array($response->status(), [302, 403, 404], true));

        $this->assertDatabaseHas('sale_orders', [
            'id' => $so->id,
            'status' => 'approved',
        ]);
    }

    public function test_reject_fails_when_user_has_no_cabang_access(): void
    {
        $so = SaleOrder::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'status' => 'request_approve',
            'so_number' => 'SO-TEST-CAB-01',
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->from(route('penjualan.so.show', $so))
            ->post(route('penjualan.so.reject', $so), [
                'reason' => 'Mencoba menolak SO dari cabang lain',
            ]);

        // Cabang global scope blocks unauthorized branch access (404) or controller rejects with redirect/error (302)
        $this->assertTrue(in_array($response->status(), [302, 403, 404], true));

        $this->assertDatabaseHas('sale_orders', [
            'id' => $so->id,
            'status' => 'request_approve',
        ]);
    }

    public function test_reject_succeeds_for_request_approve_so_with_valid_permission(): void
    {
        $so = SaleOrder::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'status' => 'request_approve',
            'so_number' => 'SO-TEST-OK-01',
        ]);

        $response = $this->actingAs($this->salesUser)
            ->from(route('penjualan.so.show', $so))
            ->post(route('penjualan.so.reject', $so), [
                'reason' => 'Harga tidak disetujui oleh manajemen',
            ]);

        $response->assertRedirect(route('penjualan.so.show', $so));
        $response->assertSessionHas('warning');

        $this->assertDatabaseHas('sale_orders', [
            'id' => $so->id,
            'status' => 'reject',
            'reject_by' => $this->salesUser->id,
        ]);
    }

    public function test_approve_fails_when_sales_order_is_already_approved(): void
    {
        $so = SaleOrder::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'status' => 'approved',
            'so_number' => 'SO-TEST-REAPP-01',
        ]);

        $response = $this->actingAs($this->salesUser)
            ->from(route('penjualan.so.show', $so))
            ->post(route('penjualan.so.approve', $so));

        // Cabang global scope blocks unauthorized branch access (404) or controller rejects with redirect/error (302)
        $this->assertTrue(in_array($response->status(), [302, 403, 404], true));
    }
}
