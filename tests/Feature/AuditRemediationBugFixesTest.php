<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockOpname;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CreditValidationService;
use App\Services\DeliveryOrderService;
use App\Services\StockOpnameService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditRemediationBugFixesTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'TEST-CABANG'],
            ['nama' => 'Cabang Uji Coba', 'alamat' => 'Jl. Uji No. 1', 'telepon' => '021123456', 'status' => true]
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete inventory stock', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create product', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view any product', 'guard_name' => 'web']);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
        $this->user->assignRole($role);
    }

    /**
     * Test BUG-02: Credit usage accurately calculates remaining balance for partial/unpaid receivables.
     */
    public function test_credit_usage_counts_all_active_remaining_receivables(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-BUG02',
            'name' => 'Customer Bug 02',
            'perusahaan' => 'PT Uji Kredit',
            'nik_npwp' => '123456789012345',
            'address' => 'Jl. Uji Kredit',
            'phone' => '08123456789',
            'tipe' => 'PKP',
            'tipe_pembayaran' => 'Kredit',
            'tempo_kredit' => 30,
            'kredit_limit' => 100000000,
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'invoice_number' => 'INV-TEST-BUG02',
            'from_model_type' => \App\Models\SuratJalan::class,
            'from_model_id' => 1,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $customer->name,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000000,
            'total' => 50000000,
            'status' => 'partially_paid',
        ]));

        AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'total' => 50000000,
            'paid' => 15000000,
            'remaining' => 35000000,
            'status' => PaymentStatus::UNPAID->value,
        ]);

        $creditService = app(CreditValidationService::class);
        $usage = $creditService->getCurrentCreditUsage($customer);

        $this->assertEquals(35000000.0, $usage, 'Credit usage harus mencatat sisa outstanding tagihan Rp 35.000.000');
    }

    /**
     * Test BUG-03: Approved stock opname cannot be deleted even by Super Admin.
     */
    public function test_approved_stock_opname_cannot_be_deleted(): void
    {
        $this->actingAs($this->user);

        $warehouse = Warehouse::firstOrCreate(
            ['kode' => 'WH-TEST'],
            ['name' => 'Gudang Uji', 'cabang_id' => $this->cabang->id, 'location' => 'Jakarta', 'status' => true]
        );

        $opname = StockOpname::create([
            'opname_number' => 'SO-APP-001',
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $this->cabang->id,
            'opname_date' => now(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $response = $this->delete(route('gudang.opname.destroy', $opname));

        $response->assertRedirect(route('gudang.opname.show', $opname));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('stock_opnames', ['id' => $opname->id, 'deleted_at' => null]);
    }

    /**
     * Test BUG-05: DeliveryOrderLog fallback handles unauthenticated or null safely.
     */
    public function test_delivery_order_log_handles_auth_safely(): void
    {
        Auth::logout();

        $service = app(DeliveryOrderService::class);

        // createLog shouldn't crash with FK violation or throw
        $service->createLog(
            delivery_order_id: 999999,
            status: 'draft',
            comments: 'Testing safe fallback',
            action: 'test'
        );

        $this->assertDatabaseHas('delivery_order_logs', [
            'delivery_order_id' => 999999,
            'comments' => 'Testing safe fallback',
        ]);
    }

    /**
     * Test BUG-07: Soft-deleted product code/sku can be re-registered without unique collision.
     */
    public function test_soft_deleted_sku_can_be_re_registered(): void
    {
        $this->actingAs($this->user);

        $category = ProductCategory::firstOrCreate(
            ['kode' => 'KAT-TEST'],
            ['name' => 'Kategori Uji', 'cabang_id' => $this->cabang->id]
        );

        $uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pcs'],
            ['abbreviation' => 'PCS']
        );

        $product = Product::create([
            'sku' => 'SKU-REUSE-001',
            'name' => 'Produk Lama',
            'product_category_id' => $category->id,
            'cabang_id' => $this->cabang->id,
            'cost_price' => 10000,
            'sell_price' => 15000,
            'tipe_pajak' => 'Non Pajak',
            'kode_merk' => 'MERK-A',
            'uom_id' => $uom->id,
        ]);

        // Soft delete produk lama
        $product->delete();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // Daftarkan produk baru dengan SKU yang sama
        $response = $this->post(route('master.products.store'), [
            'sku' => 'SKU-REUSE-001',
            'name' => 'Produk Baru SKU Sama',
            'product_category_id' => $category->id,
            'cabang_id' => $this->cabang->id,
            'cost_price' => 12000,
            'sell_price' => 18000,
            'tipe_pajak' => 'Non Pajak',
            'kode_merk' => 'MERK-B',
            'uom_id' => $uom->id,
        ]);

        $response->assertSessionDoesntHaveErrors(['sku']);
        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-REUSE-001',
            'name' => 'Produk Baru SKU Sama',
            'deleted_at' => null,
        ]);
    }
}
