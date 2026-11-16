<?php

namespace Tests\Feature\Purchasing;

use App\Models\Cabang;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Keputusan Ko Handy #6: PO berharga tidak boleh dilihat staf gudang murni.
 */
class PurchaseOrderPriceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function po(): PurchaseOrder
    {
        $cabang = Cabang::factory()->create();
        $supplier = Supplier::factory()->create();

        return PurchaseOrder::factory()->create([
            'cabang_id' => $cabang->id,
            'supplier_id' => $supplier->id,
            'status' => 'approved',
        ]);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['manage_type' => ['all']]);
        $user->assignRole($role);
        MenuAccess::flush();

        return $user;
    }

    public function test_warehouse_staff_cannot_open_priced_purchase_order(): void
    {
        $po = $this->po();
        foreach (['Warehouse Staff', 'Checker', 'Admin Inventory'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertFalse(MenuAccess::canSeePurchasePrices($user), "$role seharusnya tidak melihat harga");
            $this->actingAs($user)->get(route('pembelian.po.show', $po))->assertForbidden();
            $this->actingAs($user)->get(route('pembelian.po.index'))->assertForbidden();
        }
    }

    public function test_purchasing_finance_and_managers_can_open_purchase_order(): void
    {
        $po = $this->po();
        foreach (['Purchasing', 'Finance Manager', 'Inventory Manager', 'Super Admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue(MenuAccess::canSeePurchasePrices($user), "$role seharusnya boleh melihat harga");
            $this->actingAs($user)->get(route('pembelian.po.show', $po))->assertOk();
        }
    }
}
