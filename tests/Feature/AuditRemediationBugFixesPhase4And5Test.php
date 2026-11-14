<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditRemediationBugFixesPhase4And5Test extends TestCase
{
    use DatabaseTransactions;

    protected User $superUser;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected ChartOfAccount $cashCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'TEST-CBG-PH45'],
            ['nama' => 'Cabang Test Phase 4-5', 'alamat' => 'Jl. Uji Phase 4-5', 'telepon' => '021998877', 'status' => true]
        );

        $this->warehouse = Warehouse::firstOrCreate(
            ['kode' => 'WH-TEST-PH45'],
            [
                'name' => 'Gudang Test Phase 4-5',
                'cabang_id' => $this->cabang->id,
                'location' => 'Area Uji Coba Gudang',
                'telepon' => '021112233',
                'status' => 1,
            ]
        );

        $this->cashCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1111.99'],
            ['name' => 'Kas Test Operasional', 'type' => 'Asset', 'is_active' => true]
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete stock transfer', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create cash bank transaction', 'guard_name' => 'web']);

        $this->superUser = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
        $this->superUser->assignRole($role);
    }

    /**
     * Test BUG-NEW-01: Vendor payment prevents concurrent over-payment on invoice.
     */
    public function test_vendor_payment_rejects_overpayment_beyond_invoice_remaining(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUPP-NEW01',
            'perusahaan' => 'PT Supplier Bug 01',
            'address' => 'Jl. Industri Supplier 01',
            'phone' => '0215551234',
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'invoice_number' => 'INV-SUPP-NEW01',
            'from_model_type' => Supplier::class,
            'from_model_id' => $supplier->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $supplier->perusahaan,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 10000000,
            'total' => 10000000,
            'status' => 'unpaid',
        ]));

        AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'total' => 10000000,
            'paid' => 10000000,
            'remaining' => 0, // Sudah lunas oleh transaksi sebelumnya
            'status' => PaymentStatus::PAID->value,
        ]);

        $response = $this->actingAs($this->superUser)->post(route('pembelian.payments.store'), [
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'selected_invoices' => [$invoice->id],
            'allocations' => [
                $invoice->id => 10000000,
            ],
            'action' => 'save_and_post',
        ]);

        $response->assertSessionHasErrors(['allocations']);
    }

    /**
     * Test BUG-NEW-02: Customer receipt rejects receipt on already paid invoice.
     */
    public function test_customer_receipt_rejects_receipt_on_already_paid_invoice(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-NEW02',
            'name' => 'Customer Bug 02 AR',
            'perusahaan' => 'PT Pelanggan Lunas',
            'nik_npwp' => '112233445566778',
            'address' => 'Jl. Pelanggan No. 2',
            'phone' => '081299998888',
            'tipe' => 'PKP',
            'tipe_pembayaran' => 'Bebas',
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'invoice_number' => 'INV-CUST-NEW02',
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $customer->name,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 5000000,
            'total' => 5000000,
            'status' => 'paid',
        ]));

        AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'total' => 5000000,
            'paid' => 5000000,
            'remaining' => 0, // Sudah lunas
            'status' => PaymentStatus::PAID->value,
        ]);

        $response = $this->actingAs($this->superUser)->post(route('penjualan.receipts.store'), [
            'customer_id' => $customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'payment_reference' => 'REF-CUST-02',
            'coa_id' => $this->cashCoa->id,
            'invoice_receipts' => [
                $invoice->id => 5000000,
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('customer_receipts', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Test BUG-NEW-03: Stock transfer approved cannot be deleted even by Super Admin.
     */
    public function test_stock_transfer_approved_cannot_be_deleted_even_by_super_admin(): void
    {
        $warehouseTo = Warehouse::create([
            'kode' => 'WH-DEST-PH45',
            'name' => 'Gudang Tujuan Phase 4-5',
            'cabang_id' => $this->cabang->id,
            'location' => 'Area Tujuan Transfer',
            'telepon' => '021112244',
            'status' => 1,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $warehouseTo->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Approved', // Sudah disetujui / mutasi fisik jalan
            'notes' => 'Transfer uji coba audit',
        ]);

        $response = $this->actingAs($this->superUser)->delete(route('gudang.transfer.destroy', $transfer));

        $response->assertRedirect(route('gudang.transfer.show', $transfer));
        $response->assertSessionHas('error');
        $errorMsg = session('error');
        $this->assertStringContainsString('Hanya transfer stok berstatus Draft yang dapat dihapus', $errorMsg);

        // Dokumen transfer wajib tetap ada di database
        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => 'Approved',
            'deleted_at' => null,
        ]);
    }

    /**
     * Test BUG-NEW-04: Purchase receipt (GRN) rejected when PO is draft or cancelled.
     */
    public function test_purchase_receipt_rejected_when_po_is_draft_or_cancelled(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUPP-NEW04',
            'perusahaan' => 'PT Supplier Bug 04',
            'address' => 'Jl. Industri Supplier 04',
            'phone' => '0215551235',
            'cabang_id' => $this->cabang->id,
        ]);

        $uom = UnitOfMeasure::firstOrCreate(['name' => 'Pcs'], ['abbreviation' => 'pcs']);
        $category = ProductCategory::firstOrCreate(['kode' => 'CAT-TEST-BB'], ['name' => 'Bahan Baku']);
        $product = Product::create([
            'sku' => 'SKU-PO-NEW04',
            'name' => 'Bahan Baku Uji GRN',
            'kode_merk' => 'MRK01',
            'product_category_id' => $category->id,
            'unit_id' => $uom->id,
            'uom_id' => $uom->id,
            'cost_price' => 25000,
            'selling_price' => 30000,
            'tipe' => 'inventory',
            'status' => 'active',
            'cabang_id' => $this->cabang->id,
        ]);

        $poDraft = PurchaseOrder::create([
            'po_number' => 'PO-DRAFT-NEW04',
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft', // Bukan approved
            'subtotal' => 250000,
            'total' => 250000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $poDraft->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 25000,
            'subtotal' => 250000,
        ]);

        $response = $this->actingAs($this->superUser)->post(route('gudang.penerimaan.store'), [
            'purchase_order_id' => $poDraft->id,
            'receipt_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'action' => 'save_draft',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'qty_received' => 10,
                    'qty_accepted' => 10,
                    'qty_rejected' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['purchase_order_id']);
        $this->assertDatabaseMissing('purchase_receipts', [
            'purchase_order_id' => $poDraft->id,
        ]);
    }

    /**
     * Test BUG-NEW-05: Manufacturing production rejected when exceeding SPK planned quantity or on inactive SPK.
     */
    public function test_manufacturing_production_rejected_when_exceeding_spk_planned_qty(): void
    {
        $uom = UnitOfMeasure::firstOrCreate(['name' => 'Botol'], ['abbreviation' => 'btl']);
        $category = ProductCategory::firstOrCreate(['kode' => 'CAT-TEST-BJ'], ['name' => 'Barang Jadi']);
        $product = Product::create([
            'sku' => 'SKU-FG-NEW05',
            'name' => 'Produk Jadi Botol Uji',
            'kode_merk' => 'MRK01',
            'product_category_id' => $category->id,
            'unit_id' => $uom->id,
            'uom_id' => $uom->id,
            'cost_price' => 50000,
            'selling_price' => 75000,
            'tipe' => 'inventory',
            'status' => 'active',
            'cabang_id' => $this->cabang->id,
        ]);

        $bom = BillOfMaterial::create([
            'code' => 'BOM-TEST-NEW05',
            'nama_bom' => 'Formula BOM Test Botol',
            'product_id' => $product->id,
            'quantity' => 1,
            'uom_id' => $uom->id,
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        $plan = ProductionPlan::create([
            'plan_number' => 'PP-TEST-NEW05',
            'name' => 'Rencana Produksi Uji',
            'product_id' => $product->id,
            'bill_of_material_id' => $bom->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'uom_id' => $uom->id,
            'quantity' => 100, // Rencana produksi hanya 100 pcs
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'scheduled',
        ]);

        $spk = ManufacturingOrder::create([
            'mo_number' => 'SPK-TEST-NEW05',
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        // Coba input kuantitas produksi 150 (melebihi rencana 100 pcs)
        $response = $this->actingAs($this->superUser)->post(route('manufaktur.production.store'), [
            'production_number' => 'LHP-TEST-NEW05-A',
            'manufacturing_order_id' => $spk->id,
            'quantity_produced' => 150,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors(['quantity_produced']);
        $this->assertDatabaseMissing('productions', [
            'production_number' => 'LHP-TEST-NEW05-A',
        ]);
    }
}

