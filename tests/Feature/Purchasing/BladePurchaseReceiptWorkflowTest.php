<?php

namespace Tests\Feature\Purchasing;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladePurchaseReceiptWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;

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
            'view any purchase order',
            'view purchase order',
            'create purchase order',
            'update purchase order',
            'delete purchase order',
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
            'code' => 'SUP-RECEIPT-01',
            'perusahaan' => 'PT Supplier Logistik Utama',
            'status' => 1,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = Warehouse::where('status', 1)->first() ?? Warehouse::create([
            'kode' => 'GDG-RCV-01',
            'name' => 'Gudang Utama Penerimaan',
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

        $invCoa = ChartOfAccount::where('type', 'Asset')->first();
        $unbilledCoa = ChartOfAccount::where('type', 'Liability')->first();

        $this->product = Product::first() ?? Product::create([
            'name' => 'Baut Baja M10 x 50mm',
            'sku' => 'BB-M10-50',
            'unit_id' => $uom->id,
            'cabang_id' => $this->cabang->id,
            'inventory_coa_id' => $invCoa?->id,
            'unbilled_purchase_coa_id' => $unbilledCoa?->id,
            'status' => 1,
        ]);
    }

    /**
     * Helper to create an approved Purchase Order with items.
     */
    protected function createApprovedPo(float $qty = 50, float $price = 10000): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'total_amount' => $qty * $price,
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        \App\Models\PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $this->currency->id,
            'nominal' => 1.0,
            'type' => 'base',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'unit_price' => $price,
            'original_unit_price' => $price,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->currency->id,
            'subtotal' => $qty * $price,
        ]);

        $po->load(['purchaseOrderItem', 'purchaseOrderCurrency']);

        return $po;
    }

    public function test_penerimaan_index_page_loads_and_displays_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('gudang.penerimaan.index'));

        $response->assertStatus(200);
        $response->assertSee('Penerimaan Barang Pembelian');
        $response->assertSee('Total Penerimaan');
        $response->assertSee('Draf Penerimaan');
        $response->assertSee('Selesai Masuk Stok');
        $response->assertSee('+ Terima Barang Baru');
    }

    public function test_penerimaan_create_page_loads_with_pos_and_warehouses(): void
    {
        $po = $this->createApprovedPo(25);

        $response = $this->actingAs($this->user)->get(route('gudang.penerimaan.create', ['purchase_order_id' => $po->id]));

        $response->assertStatus(200);
        $response->assertSee('Pencatatan Penerimaan Barang');
        $response->assertSee($po->po_number);
        $response->assertSee('No. Surat Jalan Vendor');
        $response->assertSee('Simpan Draf');
        $response->assertSee('Konfirmasi Masuk Stok');
    }

    public function test_po_items_ajax_endpoint_returns_json_with_remaining_quantities(): void
    {
        $po = $this->createApprovedPo(30);

        $response = $this->actingAs($this->user)->getJson(route('gudang.penerimaan.po-items', $po));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'purchase_order' => [
                'id' => $po->id,
                'po_number' => $po->po_number,
            ],
        ]);
        $response->assertJsonFragment([
            'ordered_qty' => 30.0,
            'remaining_received' => 30.0,
        ]);
    }

    public function test_validation_blocks_over_receiving(): void
    {
        $po = $this->createApprovedPo(10);
        $poItem = $po->purchaseOrderItem->first();

        $payload = [
            'purchase_order_id' => $po->id,
            'receipt_date' => now()->toDateString(),
            'received_by' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'action' => 'save_draft',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'qty_received' => 25, // Exceeds remaining (10)
                    'qty_accepted' => 25,
                    'qty_rejected' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('gudang.penerimaan.store'), $payload);

        $response->assertSessionHasErrors(['items.0.qty_received']);
    }

    public function test_can_create_draft_purchase_receipt(): void
    {
        $po = $this->createApprovedPo(40);
        $poItem = $po->purchaseOrderItem->first();

        $payload = [
            'purchase_order_id' => $po->id,
            'receipt_date' => now()->toDateString(),
            'supplier_delivery_note_number' => 'SJ-VENDOR-12345',
            'received_by' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Tiba dalam kondisi kardus rapi.',
            'action' => 'save_draft',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'qty_received' => 20,
                    'qty_accepted' => 20,
                    'qty_rejected' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('gudang.penerimaan.store'), $payload);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('draft', $receipt->status);
        $this->assertEquals('SJ-VENDOR-12345', $receipt->supplier_delivery_note_number);
        $this->assertStringContainsString('Tiba dalam kondisi kardus rapi', $receipt->clean_notes);

        $response->assertRedirect(route('gudang.penerimaan.show', $receipt));
    }

    public function test_can_create_and_directly_confirm_purchase_receipt(): void
    {
        $po = $this->createApprovedPo(30);
        $poItem = $po->purchaseOrderItem->first();

        $payload = [
            'purchase_order_id' => $po->id,
            'receipt_date' => now()->toDateString(),
            'supplier_delivery_note_number' => 'SJ-CONFIRM-999',
            'received_by' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'action' => 'save_and_confirm',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'qty_received' => 30,
                    'qty_accepted' => 30,
                    'qty_rejected' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('gudang.penerimaan.store'), $payload);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('completed', $receipt->status);

        // Check PO status automatically cascaded
        $po->refresh();
        $this->assertEquals('completed', $po->status);

        $response->assertRedirect(route('gudang.penerimaan.show', $receipt));
    }

    public function test_confirm_draft_purchase_receipt_flow(): void
    {
        $po = $this->createApprovedPo(50);
        $poItem = $po->purchaseOrderItem->first();

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-TEST-0001',
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
        ]);

        $item = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 25,
            'qty_accepted' => 25,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->post(route('gudang.penerimaan.confirm', $receipt));

        $receipt->refresh();
        $this->assertEquals('completed', $receipt->status);

        $po->refresh();
        $this->assertEquals('partially_received', $po->status);

        $response->assertRedirect(route('gudang.penerimaan.show', $receipt));
    }

    public function test_penerimaan_show_page_renders_stepper_and_details(): void
    {
        $po = $this->createApprovedPo(20);
        $poItem = $po->purchaseOrderItem->first();

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-20261021-9988',
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'notes' => '[SJ Vendor: SJ-VENDOR-777]\nBarang diterima lengkap.',
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 20,
            'qty_accepted' => 20,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get(route('gudang.penerimaan.show', $receipt));

        $response->assertStatus(200);
        $response->assertSee('GRN-20261021-9988');
        $response->assertSee('SJ-VENDOR-777');
        $response->assertSee('Barang diterima lengkap');
        $response->assertSee('Selesai Masuk Stok');
        $response->assertSee('Cetak Surat Jalan Masuk');
    }

    public function test_penerimaan_edit_page_loads_for_draft_and_blocks_completed(): void
    {
        $po = $this->createApprovedPo(20);
        $poItem = $po->purchaseOrderItem->first();

        $draftReceipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-DRAFT-01',
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $draftReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        // Draft receipt should be editable
        $responseDraft = $this->actingAs($this->user)->get(route('gudang.penerimaan.edit', $draftReceipt));
        $responseDraft->assertStatus(200);
        $responseDraft->assertSee('Edit Draf Penerimaan Barang');

        // Completed receipt should redirect back
        $draftReceipt->update(['status' => 'completed']);
        $responseCompleted = $this->actingAs($this->user)->get(route('gudang.penerimaan.edit', $draftReceipt));
        $responseCompleted->assertRedirect(route('gudang.penerimaan.show', $draftReceipt));
    }

    public function test_stream_penerimaan_pdf(): void
    {
        $po = $this->createApprovedPo(15);
        $poItem = $po->purchaseOrderItem->first();

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-PDF-TEST-01',
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 15,
            'qty_accepted' => 15,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get(route('gudang.penerimaan.pdf', $receipt));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_rejected_items_creates_purchase_return(): void
    {
        $po = $this->createApprovedPo(30);
        $poItem = $po->purchaseOrderItem->first();

        $payload = [
            'purchase_order_id' => $po->id,
            'receipt_date' => now()->toDateString(),
            'supplier_delivery_note_number' => 'SJ-REJECT-01',
            'received_by' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'action' => 'save_draft',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'qty_received' => 20,
                    'qty_accepted' => 15,
                    'qty_rejected' => 5,
                    'reason_rejected' => 'Kemasan rusak dan penyok',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('gudang.penerimaan.store'), $payload);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $item = $receipt->purchaseReceiptItem->first();
        $this->assertEquals(15, (float) $item->qty_accepted);
        $this->assertEquals(5, (float) $item->qty_rejected);
        $this->assertEquals('Kemasan rusak dan penyok', $item->reason_rejected);

        // Verify PurchaseReturn was auto-drafted by PurchaseReceiptObserver
        $return = \App\Models\PurchaseReturn::where('purchase_receipt_id', $receipt->id)->first();
        $this->assertNotNull($return);
        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_return_id' => $return->id,
            'qty_returned' => 5,
        ]);
    }
}
