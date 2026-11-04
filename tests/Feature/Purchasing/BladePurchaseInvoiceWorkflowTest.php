<?php

namespace Tests\Feature\Purchasing;

use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladePurchaseInvoiceWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;
    protected PurchaseOrder $purchaseOrder;
    protected PurchaseReceipt $purchaseReceipt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST',
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Test No. 1',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any invoice',
            'view invoice',
            'create invoice',
            'update invoice',
            'delete invoice',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (!$this->user->hasRole('Super Admin')) {
            $this->user->assignRole('Super Admin');
        }

        $this->supplier = Supplier::first() ?? Supplier::create([
            'code' => 'SUP-INV-01',
            'perusahaan' => 'PT Sumber Logam Abadi',
            'name' => 'Budi Santoso',
            'tempo_hutang' => 30,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = Warehouse::where('status', 1)->first() ?? Warehouse::create([
            'kode' => 'GDG-INV-01',
            'name' => 'Gudang Pusat Penerimaan',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'exchange_rate' => 1,
            'status' => 1,
        ]);

        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Pcs', 'code' => 'PCS']);

        $invCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.inventory', '11300'),
        ], [
            'name' => 'Persediaan Barang',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $apCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.accounts_payable', '21100'),
        ], [
            'name' => 'Hutang Dagang',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $unbilledCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.unbilled_purchase', '21199'),
        ], [
            'name' => 'Pembelian Belum Tertagih',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $ppnMasukanCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.ppn_masukan', '11800'),
        ], [
            'name' => 'PPN Masukan',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->product = Product::first() ?? Product::create([
            'name' => 'Plat Baja 5mm',
            'sku' => 'PB-5MM-TEST',
            'unit_id' => $uom->id,
            'cabang_id' => $this->cabang->id,
            'inventory_coa_id' => $invCoa->id,
            'unbilled_purchase_coa_id' => $unbilledCoa->id,
            'status' => 1,
        ]);

        // Buat Purchase Order disetujui
        $uniqueNum = uniqid();
        $this->purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-INV-' . $uniqueNum,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->subDays(5),
            'status' => 'approved',
            'subtotal' => 200000,
            'tax_amount' => 22000,
            'total_amount' => 222000,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 20000,
            'subtotal' => 200000,
            'tax' => 11,
            'total' => 222000,
        ]);

        // Buat Goods Receipt (GRN) completed
        $this->purchaseReceipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-INV-' . $uniqueNum,
            'purchase_order_id' => $this->purchaseOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'receipt_date' => now()->subDays(2),
            'status' => 'completed',
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $this->purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_invoice_index_page_loads_and_displays_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('pembelian.invoices.index'));

        $response->assertOk();
        $response->assertViewIs('pembelian.invoice.index');
        $response->assertSee('Faktur Pembelian');
        $response->assertSee('Total Tagihan');
        $response->assertSee('Belum Dibayar');
        $response->assertSee('Jatuh Tempo');
    }

    public function test_invoice_create_page_loads_with_suppliers(): void
    {
        $response = $this->actingAs($this->user)->get(route('pembelian.invoices.create'));

        $response->assertOk();
        $response->assertViewIs('pembelian.invoice.create');
        $response->assertSee('Buat Faktur Baru');
        $response->assertSee('Pemasok');
    }

    public function test_ajax_available_receipts_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('pembelian.invoices.available-receipts', $this->supplier)
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonFragment([
            'receipt_number' => $this->purchaseReceipt->receipt_number,
        ]);
    }

    public function test_ajax_receipt_details_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('pembelian.invoices.receipt-details', ['receipt_ids' => [$this->purchaseReceipt->id]])
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_validation_blocks_empty_receipts(): void
    {
        $response = $this->actingAs($this->user)->post(route('pembelian.invoices.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_receipts' => [],
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['purchase_receipts']);
    }

    public function test_can_create_draft_purchase_invoice(): void
    {
        $response = $this->actingAs($this->user)->post(route('pembelian.invoices.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_receipts' => [$this->purchaseReceipt->id],
            'supplier_invoice_number' => 'INV-SUP-TEST-DRAFT',
            'tax_invoice_number' => '010.000-26.99990001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'action' => 'save_draft',
            'other_fee' => 10000,
        ]);

        $response->assertRedirect();
        $invoice = Invoice::where('supplier_invoice_number', 'INV-SUP-TEST-DRAFT')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertEquals(10, $invoice->invoiceItem->first()->quantity);
        $this->assertCount(1, $invoice->invoiceItem);
    }

    public function test_can_create_and_directly_post_purchase_invoice_with_gl_and_ap(): void
    {
        // Buat GRN baru agar tidak konflik dengan receipt yang sudah tertagih
        $uniqueNum = uniqid();
        $po = PurchaseOrder::create([
            'po_number' => 'PO-POST-' . $uniqueNum,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'subtotal' => 100000,
            'total_amount' => 111000,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,
        ]);
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 20000,
            'subtotal' => 100000,
            'tax' => 11,
            'total' => 111000,
        ]);
        $rcp = PurchaseReceipt::create([
            'receipt_number' => 'GRN-POST-' . $uniqueNum,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'receipt_date' => now(),
            'status' => 'completed',
        ]);
        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $rcp->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 5,
            'qty_accepted' => 5,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('pembelian.invoices.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_receipts' => [$rcp->id],
            'supplier_invoice_number' => 'INV-SUP-POST-' . $uniqueNum,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'action' => 'save_and_post',
            'other_fee' => 0,
        ]);

        $response->assertRedirect();
        $invoice = Invoice::where('supplier_invoice_number', 'INV-SUP-POST-' . $uniqueNum)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status);

        // Assert Account Payable created
        $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ap);
        $this->assertEquals((float)$invoice->total, (float)$ap->total);

        // Assert Journal Entry created
        $journal = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->first();
        $this->assertNotNull($journal);
    }

    public function test_can_post_draft_purchase_invoice(): void
    {
        $uniqueNum = uniqid();
        $invoice = Invoice::create([
            'invoice_number' => 'PINV-DRAFT-' . $uniqueNum,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 25000,
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $response = $this->actingAs($this->user)->post(route('pembelian.invoices.post', $invoice));

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status);
    }

    public function test_show_page_renders_stepper_and_3_way_matching(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'PINV-SHOW-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'purchase_order_ids' => [$this->purchaseOrder->id],
            'purchase_receipts' => [$this->purchaseReceipt->id],
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100000,
            'total' => 100000,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->actingAs($this->user)->get(route('pembelian.invoices.show', $invoice));

        $response->assertOk();
        $response->assertViewIs('pembelian.invoice.show');
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('Pemeriksaan 3-Way Matching');
        $response->assertSee('Rincian Barang');
    }

    public function test_draft_invoice_can_be_edited_and_deleted(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'PINV-EDIT-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        // Edit page loads
        $response = $this->actingAs($this->user)->get(route('pembelian.invoices.edit', $invoice));
        $response->assertOk();

        // Update works
        $response = $this->actingAs($this->user)->put(route('pembelian.invoices.update', $invoice), [
            'supplier_invoice_number' => 'INV-SUP-UPDATED',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(45)->toDateString(),
        ]);
        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals('INV-SUP-UPDATED', $invoice->supplier_invoice_number);

        // Delete works
        $deleteResponse = $this->actingAs($this->user)->delete(route('pembelian.invoices.destroy', $invoice));
        $deleteResponse->assertRedirect(route('pembelian.invoices.index'));
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_posted_invoice_cannot_be_deleted(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'PINV-LOCK-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->actingAs($this->user)->delete(route('pembelian.invoices.destroy', $invoice));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }

    public function test_can_cancel_posted_invoice_with_reverse_journals(): void
    {
        $uniqueNum = uniqid();
        $invoice = Invoice::create([
            'invoice_number' => 'PINV-CNC-' . $uniqueNum,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => Invoice::STATUS_SENT,
        ]);

        AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 50000,
            'remaining' => 50000,
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('pembelian.invoices.cancel', $invoice), [
            'cancel_reason' => 'Kesalahan penagihan dari supplier.',
        ]);

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_CANCELLED, $invoice->status);
        $this->assertEquals('Kesalahan penagihan dari supplier.', $invoice->cancel_reason);
    }

    public function test_stream_purchase_invoice_pdf(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'PINV-PDF-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->actingAs($this->user)->get(route('pembelian.invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }
}
