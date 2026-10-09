<?php

namespace Tests\Feature;

use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\DeliveryOrderPolicy;
use App\Policies\ManufacturingOrderPolicy;
use App\Policies\ProductionPlanPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\SaleOrderPolicy;
use App\Services\ManufacturingJournalService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesDeliveryManufacturingIntegrityP0Test extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangA;
    protected Cabang $cabangB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;
    protected Customer $customerA;
    protected Customer $customerB;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Currency $currency;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uom = UnitOfMeasure::factory()->create();

        $this->cabangA = Cabang::create([
            'kode' => 'CBA',
            'nama' => 'Cabang Alfa',
            'alamat' => 'Alamat A',
        ]);

        $this->cabangB = Cabang::create([
            'kode' => 'CBB',
            'nama' => 'Cabang Beta',
            'alamat' => 'Alamat B',
        ]);

        $this->warehouseA = Warehouse::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'name' => 'Gudang Alfa',
        ]);

        $this->warehouseB = Warehouse::factory()->create([
            'cabang_id' => $this->cabangB->id,
            'name' => 'Gudang Beta',
        ]);

        $this->currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'exchange_rate' => 1.0,
            'to_rupiah' => 1.0,
            'is_default' => true,
        ]);

        ChartOfAccount::firstOrCreate(
            ['code' => '1120'],
            ['name' => 'Piutang Usaha', 'type' => 'Asset']
        );
        ChartOfAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Pendapatan Penjualan', 'type' => 'Revenue']
        );
        ChartOfAccount::firstOrCreate(
            ['code' => '5100.10'],
            ['name' => 'HPP Penjualan', 'type' => 'Expense']
        );
        ChartOfAccount::firstOrCreate(
            ['code' => '1140.20'],
            ['name' => 'Barang Dalam Pengiriman', 'type' => 'Asset']
        );
        ChartOfAccount::firstOrCreate(
            ['code' => '1140.10'],
            ['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset']
        );

        // Permissions & Roles setup
        Permission::firstOrCreate(['name' => 'view any quotation']);
        Permission::firstOrCreate(['name' => 'view quotation']);
        Permission::firstOrCreate(['name' => 'update quotation']);
        Permission::firstOrCreate(['name' => 'delete quotation']);
        Permission::firstOrCreate(['name' => 'create quotation']);
        Permission::firstOrCreate(['name' => 'view any sales order']);
        Permission::firstOrCreate(['name' => 'view sales order']);
        Permission::firstOrCreate(['name' => 'create sales order']);
        Permission::firstOrCreate(['name' => 'update sales order']);
        Permission::firstOrCreate(['name' => 'delete sales order']);
        Permission::firstOrCreate(['name' => 'view any delivery order']);
        Permission::firstOrCreate(['name' => 'view delivery order']);
        Permission::firstOrCreate(['name' => 'delete delivery order']);
        Permission::firstOrCreate(['name' => 'update delivery order']);
        Permission::firstOrCreate(['name' => 'view any production plan']);
        Permission::firstOrCreate(['name' => 'view production plan']);
        Permission::firstOrCreate(['name' => 'delete production plan']);
        Permission::firstOrCreate(['name' => 'view any manufacturing order']);
        Permission::firstOrCreate(['name' => 'view manufacturing order']);
        Permission::firstOrCreate(['name' => 'delete manufacturing order']);

        $roleAdmin = Role::firstOrCreate(['name' => 'Admin']);
        $roleAdmin->givePermissionTo(Permission::all());

        $this->userA = User::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'manage_type' => 'cabang',
        ]);
        $this->userA->assignRole($roleAdmin);

        $this->userB = User::factory()->create([
            'cabang_id' => $this->cabangB->id,
            'manage_type' => 'cabang',
        ]);
        $this->userB->assignRole($roleAdmin);

        $this->superAdmin = User::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'manage_type' => 'all',
        ]);
        $this->superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super Admin']));

        $this->customerA = Customer::factory()->create([
            'name' => 'Customer Alfa',
            'cabang_id' => $this->cabangA->id,
            'tipe_pembayaran' => 'Bebas',
            'kredit_limit' => 10000000,
        ]);

        $this->customerB = Customer::factory()->create([
            'name' => 'Customer Beta',
            'cabang_id' => $this->cabangB->id,
            'tipe_pembayaran' => 'Bebas',
            'kredit_limit' => 10000000,
        ]);
    }

    public function test_sent_and_completed_delivery_order_cannot_be_deleted(): void
    {
        $doSent = DeliveryOrder::create([
            'do_number' => 'DO-SENT-001',
            'cabang_id' => $this->cabangA->id,
            'warehouse_id' => $this->warehouseA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'sent',
            'delivery_date' => now()->toDateString(),
        ]);

        $policy = new DeliveryOrderPolicy();
        $this->assertFalse($policy->delete($this->userA, $doSent), 'Policy must forbid deleting sent DO');
        $this->assertFalse($policy->forceDelete($this->superAdmin, $doSent), 'Policy must forbid force-deleting sent DO');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Surat Jalan / Delivery Order dengan status 'sent' tidak dapat dihapus");
        $doSent->delete();
    }

    public function test_completed_delivery_order_cannot_be_deleted(): void
    {
        $doCompleted = DeliveryOrder::create([
            'do_number' => 'DO-COMP-001',
            'cabang_id' => $this->cabangA->id,
            'warehouse_id' => $this->warehouseA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'completed',
            'delivery_date' => now()->toDateString(),
        ]);

        $policy = new DeliveryOrderPolicy();
        $this->assertFalse($policy->delete($this->userA, $doCompleted), 'Policy must forbid deleting completed DO');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Surat Jalan / Delivery Order dengan status 'completed' tidak dapat dihapus");
        $doCompleted->delete();
    }

    public function test_draft_delivery_order_with_linked_invoice_cannot_be_deleted(): void
    {
        $doDraft = DeliveryOrder::create([
            'do_number' => 'DO-DRAFT-001',
            'cabang_id' => $this->cabangA->id,
            'warehouse_id' => $this->warehouseA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'draft',
            'delivery_date' => now()->toDateString(),
        ]);

        // Link downstream invoice
        Invoice::create([
            'invoice_number' => 'INV-DO-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $doDraft->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'subtotal' => 500000,
            'total' => 500000,
            'remaining_balance' => 500000,
            'status' => 'unpaid',
            'cabang_id' => $this->cabangA->id,
        ]);

        $policy = new DeliveryOrderPolicy();
        $this->assertFalse($policy->delete($this->userA, $doDraft), 'Policy must forbid deleting invoiced DO');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Delivery Order tidak dapat dihapus karena sudah memiliki faktur penjualan (Invoice) terkait.");
        $doDraft->delete();
    }

    public function test_draft_delivery_order_without_invoice_can_be_deleted(): void
    {
        $doDraft = DeliveryOrder::create([
            'do_number' => 'DO-DRAFT-FREE',
            'cabang_id' => $this->cabangA->id,
            'warehouse_id' => $this->warehouseA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'draft',
            'delivery_date' => now()->toDateString(),
        ]);

        $policy = new DeliveryOrderPolicy();
        $this->assertTrue($policy->delete($this->userA, $doDraft), 'Draft DO without invoice can be deleted');

        $this->assertTrue($doDraft->delete());
        $this->assertSoftDeleted('delivery_orders', ['id' => $doDraft->id]);
    }

    public function test_self_pickup_sale_order_does_not_double_deduct_stock_when_fulfilled_via_do(): void
    {
        $product = Product::factory()->create([
            'cost_price' => 50000,
            'sell_price' => 75000,
        ]);

        // Set initial stock = 10
        app(ProductService::class)->createStockMovement(
            product_id: $product->id,
            warehouse_id: $this->warehouseA->id,
            quantity: 10,
            type: 'adjustment_in',
            date: now()->toDateString(),
            notes: 'Initial Stock',
            rak_id: null,
            fromModel: null,
            value: 500000
        );

        $stockBefore = \App\Models\InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('qty_available');
        $this->assertEquals(10, $stockBefore);

        // Create Sale Order: Ambil Sendiri, 4 pcs
        $so = SaleOrder::create([
            'so_number' => 'SO-PICKUP-001',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabangA->id,
            'order_date' => now()->toDateString(),
            'tipe_pengiriman' => 'Ambil Sendiri',
            'status' => 'approved',
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'total_amount' => 300000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity' => 4,
            'unit_price' => 75000,
            'delivered_quantity' => 0,
        ]);

        // Simulate that DO was created and delivered 4 pcs
        // DO sent reduces stock from 10 to 6
        app(ProductService::class)->createStockMovement(
            product_id: $product->id,
            warehouse_id: $this->warehouseA->id,
            quantity: 4,
            type: 'sales',
            date: now()->toDateString(),
            notes: 'DO shipment',
            rak_id: null,
            fromModel: null,
            value: 200000
        );
        $soItem->update(['delivered_quantity' => 4]);

        $stockAfterDo = \App\Models\InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('qty_available');
        $this->assertEquals(6, $stockAfterDo, 'Stock after DO shipment must be 6');

        // Now SO transitions to completed
        $so->update(['status' => 'completed']);

        // Stock MUST REMAIN 6, not double-reduced to 2!
        $stockAfterSoCompleted = \App\Models\InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('qty_available');
        $this->assertEquals(6, $stockAfterSoCompleted, 'Stock must NOT be double deducted for self-pickup when delivered via DO');
    }

    public function test_self_pickup_sale_order_deducts_stock_once_when_completed_without_do(): void
    {
        $product = Product::factory()->create([
            'cost_price' => 20000,
            'sell_price' => 30000,
        ]);

        // Set initial stock = 10
        app(ProductService::class)->createStockMovement(
            product_id: $product->id,
            warehouse_id: $this->warehouseA->id,
            quantity: 10,
            type: 'adjustment_in',
            date: now()->toDateString(),
            notes: 'Initial Stock',
            rak_id: null,
            fromModel: null,
            value: 200000
        );

        $so = SaleOrder::create([
            'so_number' => 'SO-PICKUP-NODELIVERY',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabangA->id,
            'order_date' => now()->toDateString(),
            'tipe_pengiriman' => 'Ambil Sendiri',
            'status' => 'approved',
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'total_amount' => 90000,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity' => 3,
            'unit_price' => 30000,
            'delivered_quantity' => 0,
        ]);

        // Complete the SO directly without DO
        $so->update(['status' => 'completed']);

        $stockAfter = \App\Models\InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('qty_available');
        $this->assertEquals(7, $stockAfter, 'Stock should be deducted from 10 to 7 on direct self-pickup');

        // Re-saving / re-updating should not deduct again (idempotent)
        $so->update(['total_amount' => 90000]);
        $stockAfterResave = \App\Models\InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('qty_available');
        $this->assertEquals(7, $stockAfterResave, 'Re-saving SO must be idempotent and not deduct again');
    }

    public function test_branch_isolation_on_quotation_and_production_plan(): void
    {
        // Create Quotations
        $quotationA = Quotation::create([
            'quotation_number' => 'QUO-A-001',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabangA->id,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'total_amount' => 1000000,
        ]);

        $quotationB = Quotation::create([
            'quotation_number' => 'QUO-B-001',
            'customer_id' => $this->customerB->id,
            'cabang_id' => $this->cabangB->id,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'total_amount' => 2000000,
        ]);

        // Create Production Plans
        $planProduct = Product::factory()->create();

        $planA = ProductionPlan::create([
            'plan_number' => 'PLAN-A-001',
            'name' => 'Plan Alfa',
            'product_id' => $planProduct->id,
            'uom_id' => $this->uom->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'cabang_id' => $this->cabangA->id,
            'warehouse_id' => $this->warehouseA->id,
            'status' => 'draft',
            'quantity' => 10,
            'created_by' => $this->userA->id,
        ]);

        $planB = ProductionPlan::create([
            'plan_number' => 'PLAN-B-001',
            'name' => 'Plan Beta',
            'product_id' => $planProduct->id,
            'uom_id' => $this->uom->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'cabang_id' => $this->cabangB->id,
            'warehouse_id' => $this->warehouseB->id,
            'status' => 'draft',
            'quantity' => 20,
            'created_by' => $this->userB->id,
        ]);

        // As User A (Cabang Alfa):
        $this->actingAs($this->userA);
        $visibleQuotations = Quotation::pluck('id')->all();
        $this->assertContains($quotationA->id, $visibleQuotations);
        $this->assertNotContains($quotationB->id, $visibleQuotations, 'Quotation B must not be visible to User A');

        $visiblePlans = ProductionPlan::pluck('id')->all();
        $this->assertContains($planA->id, $visiblePlans);
        $this->assertNotContains($planB->id, $visiblePlans, 'ProductionPlan B must not be visible to User A');

        // Policies must block cross-branch access
        $quotationPolicy = new QuotationPolicy();
        $this->assertTrue($quotationPolicy->view($this->userA, $quotationA));
        $this->assertFalse($quotationPolicy->view($this->userA, $quotationB), 'QuotationPolicy must forbid cross-branch view');

        $planPolicy = new ProductionPlanPolicy();
        $this->assertTrue($planPolicy->view($this->userA, $planA));
        $this->assertFalse($planPolicy->view($this->userA, $planB), 'ProductionPlanPolicy must forbid cross-branch view');
    }

    public function test_manufacturing_overhead_coa_resolves_to_overhead_not_labor(): void
    {
        // Seed labor COA and overhead COA
        $laborCoa = ChartOfAccount::create([
            'code' => '5230',
            'name' => 'BIAYA TENAGA KERJA PROSES PRODUKSI',
            'type' => 'Expense',
        ]);

        $overheadCoa = ChartOfAccount::create([
            'code' => '5130',
            'name' => 'Overhead - Listrik Pabrik',
            'type' => 'Expense',
        ]);

        $service = app(ManufacturingJournalService::class);

        // Reflection to call protected resolveDefaultManufacturingOverheadCoaId
        $reflection = new \ReflectionClass($service);
        $laborMethod = $reflection->getMethod('resolveDefaultManufacturingLaborCoaId');
        $laborMethod->setAccessible(true);
        $overheadMethod = $reflection->getMethod('resolveDefaultManufacturingOverheadCoaId');
        $overheadMethod->setAccessible(true);

        $resolvedLaborId = $laborMethod->invoke($service);
        $resolvedOverheadId = $overheadMethod->invoke($service);

        $this->assertEquals($laborCoa->id, $resolvedLaborId, 'Labor COA should resolve to 5230');
        $this->assertEquals($overheadCoa->id, $resolvedOverheadId, 'Overhead COA should resolve to 5130 (Overhead), not labor');
        $this->assertNotEquals($resolvedLaborId, $resolvedOverheadId, 'Labor and Overhead COAs must NOT be the same');
    }
}
