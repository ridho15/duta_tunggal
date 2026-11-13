<?php

namespace Tests\Feature\Purchasing\Adversarial;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderApprovalGuardTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangA;
    protected Cabang $cabangB;
    protected Currency $currency;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected User $staffPurchasing;
    protected User $purchasingManager;
    protected User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-A',
            'nama' => 'Cabang Surabaya A',
            'status' => true,
        ]);

        $this->cabangB = Cabang::create([
            'kode' => 'CBG-B',
            'nama' => 'Cabang Jakarta B',
            'alamat' => 'Jl. Sudirman No. 2, Jakarta',
            'status' => true,
        ]);

        $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
        ]);

        $poPerms = [
            'view any purchase order',
            'view purchase order',
            'create purchase order',
            'update purchase order',
            'delete purchase order',
            'response purchase order',
            'request purchase order',
        ];
        foreach ($poPerms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $roleStaff = Role::firstOrCreate(['name' => 'Staff Purchasing', 'guard_name' => 'web']);
        $roleStaff->syncPermissions($poPerms);

        $roleManager = Role::firstOrCreate(['name' => 'Purchasing Manager', 'guard_name' => 'web']);
        $roleManager->syncPermissions($poPerms);

        $roleOwner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $roleOwner->syncPermissions($poPerms);

        $this->staffPurchasing = User::factory()->create([
            'name' => 'Staff PO Tester',
            'cabang_id' => $this->cabangA->id,
            'manage_type' => ['cabang'],
        ]);
        $this->staffPurchasing->assignRole($roleStaff);

        $this->purchasingManager = User::factory()->create([
            'name' => 'Manager PO Tester',
            'cabang_id' => $this->cabangA->id,
            'manage_type' => ['cabang'],
        ]);
        $this->purchasingManager->assignRole($roleManager);

        $this->ownerUser = User::factory()->create([
            'name' => 'Owner PO Tester',
            'cabang_id' => $this->cabangA->id,
            'manage_type' => ['all'],
        ]);
        $this->ownerUser->assignRole($roleOwner);

        $this->supplier = Supplier::factory()->create([
            'code' => 'SUP-GUARD-01',
            'perusahaan' => 'PT Supplier Utama',
            'address' => 'Jl. Industri Baja No. 10, Surabaya',
            'phone' => '031-888999',
            'cabang_id' => $this->cabangA->id,
            'tempo_hutang' => 30,
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'kode' => 'WH-A',
            'name' => 'Gudang Cabang A',
            'location' => 'Kawasan Industri Surabaya',
            'cabang_id' => $this->cabangA->id,
            'status' => 1,
        ]);

        $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Bahan Baku', 'kode' => 'BB']);
        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Pcs', 'abbreviation' => 'pcs']);

        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'name' => 'Produk Tes Guard',
            'cost_price' => 100000,
            'sell_price' => 150000,
            'is_active' => true,
        ]);
    }

    /**
     * Staf pembuat PO tidak boleh melihat tombol "Setujui PO" pada draf buatannya sendiri (Anti-Self-Approval).
     */
    public function test_creator_staff_cannot_see_approve_button_on_own_draft_po(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-SELF-01',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabangA->id,
            'currency_id' => $this->currency->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'total_amount' => 5000000,
            'created_by' => $this->staffPurchasing->id,
        ]);

        $response = $this->actingAs($this->staffPurchasing)->get(route('pembelian.po.show', $po));

        $response->assertStatus(200);
        $response->assertViewHas('canApprove', false);
        $response->assertDontSee('Setujui PO');
    }

    /**
     * Pada status request_approve, staf pembuat melihat badge "Menunggu Approval Manajer" dan bukan tombol Setujui PO.
     */
    public function test_creator_staff_sees_waiting_badge_instead_of_approve_button_on_request_approve(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-SELF-02',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabangA->id,
            'currency_id' => $this->currency->id,
            'order_date' => now()->toDateString(),
            'status' => 'request_approve',
            'total_amount' => 5000000,
            'created_by' => $this->staffPurchasing->id,
        ]);

        $response = $this->actingAs($this->staffPurchasing)->get(route('pembelian.po.show', $po));

        $response->assertStatus(200);
        $response->assertViewHas('canApprove', false);
        $response->assertSee('Menunggu Approval Manajer');
        $response->assertDontSee('Ya, Setujui Sekarang');
    }

    /**
     * Purchasing Manager (bukan pembuat) berhak melihat tombol Setujui PO untuk transaksi <= Rp 10.000.000 (Tier 1).
     */
    public function test_purchasing_manager_can_see_approve_button_for_tier1_po(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-MGR-01',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabangA->id,
            'currency_id' => $this->currency->id,
            'order_date' => now()->toDateString(),
            'status' => 'request_approve',
            'total_amount' => 8000000, // <= 10 jt
            'created_by' => $this->staffPurchasing->id,
        ]);

        $response = $this->actingAs($this->purchasingManager)->get(route('pembelian.po.show', $po));

        $response->assertStatus(200);
        $response->assertViewHas('canApprove', true);
        $response->assertSee('Setujui PO');
    }

    /**
     * Purchasing Manager TIDAK dapat melihat tombol Setujui PO untuk transaksi > Rp 10.000.000 (Persetujuan bertingkat).
     */
    public function test_purchasing_manager_cannot_see_approve_button_for_tier2_po(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-MGR-TIER2',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabangA->id,
            'currency_id' => $this->currency->id,
            'order_date' => now()->toDateString(),
            'status' => 'request_approve',
            'total_amount' => 25000000, // > 10 jt
            'created_by' => $this->staffPurchasing->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 500000,
            'subtotal' => 25000000,
        ]);

        $response = $this->actingAs($this->purchasingManager)->get(route('pembelian.po.show', $po));

        $response->assertStatus(200);
        $response->assertViewHas('canApprove', false);
        $response->assertDontSee('Setujui PO');
        $response->assertSee('Menunggu Approval Manajer');
    }

    /**
     * Owner / Direksi berhak menyetujui PO > Rp 10.000.000 (Tier 2).
     */
    public function test_owner_can_see_approve_button_and_approve_tier2_po(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-OWNER-01',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabangA->id,
            'currency_id' => $this->currency->id,
            'order_date' => now()->toDateString(),
            'status' => 'request_approve',
            'total_amount' => 50000000,
            'created_by' => $this->staffPurchasing->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 5000000,
            'subtotal' => 50000000,
        ]);

        $response = $this->actingAs($this->ownerUser)->get(route('pembelian.po.show', $po));
        $response->assertStatus(200);
        $response->assertViewHas('canApprove', true);
        $response->assertSee('Setujui PO');

        $approveRes = $this->actingAs($this->ownerUser)->post(route('pembelian.po.approve', $po));
        $approveRes->assertSessionHas('success');
        $this->assertEquals('approved', $po->fresh()->status);
    }

    /**
     * User tanpa akses cabang diblokir oleh CabangScope (404) sehingga tidak dapat melihat atau menyetujui PO cabang lain.
     */
    public function test_user_without_cabang_access_cannot_approve_po(): void
    {
        $userCabangB = User::factory()->create([
            'cabang_id' => $this->cabangB->id,
            'manage_type' => ['cabang'],
        ]);

        $poCabangB = PurchaseOrder::create([
            'po_number' => 'PO-CBG-B-01',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabangB->id,
            'currency_id' => $this->currency->id,
            'order_date' => now()->toDateString(),
            'status' => 'request_approve',
            'total_amount' => 2000000,
            'created_by' => $userCabangB->id,
        ]);

        // Purchasing Manager hanya punya akses cabang A, tidak bisa mengakses PO di cabang B
        $response = $this->actingAs($this->purchasingManager)->get(route('pembelian.po.show', $poCabangB));
        $response->assertStatus(404);

        $postResponse = $this->actingAs($this->purchasingManager)->post(route('pembelian.po.approve', $poCabangB));
        $postResponse->assertStatus(404);
        $this->assertEquals('request_approve', $poCabangB->fresh()->status);
    }
}
