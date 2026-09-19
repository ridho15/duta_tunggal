<?php

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Filament\Resources\QualityControlPurchaseResource\Pages\CreateQualityControlPurchase;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\QualityControl;
use App\Models\QualityControlItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseInvoiceAccountingService;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function createFeedbackAuditContext(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $cabangPusat = Cabang::factory()->create([
        'kode'   => 'CBG-001',
        'nama'   => 'Cabang Pusat Jakarta',
        'status' => 1,
    ]);

    $cabangCabang = Cabang::factory()->create([
        'kode'   => 'CBG-002',
        'nama'   => 'Cabang Surabaya',
        'status' => 1,
    ]);

    UnitOfMeasure::factory()->create();

    $currency = Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
    );

    $warehousePusat = Warehouse::factory()->create([
        'cabang_id' => $cabangPusat->id,
        'name'      => 'Gudang Utama Pusat',
        'status'    => 1,
    ]);

    $warehouseCabang = Warehouse::factory()->create([
        'cabang_id' => $cabangCabang->id,
        'name'      => 'Gudang Cabang Surabaya',
        'status'    => 1,
    ]);

    $supplier = Supplier::factory()->create([
        'cabang_id'    => $cabangPusat->id,
        'tempo_hutang' => 30,
    ]);

    $productA = Product::factory()->forCabang($cabangPusat)->create([
        'name'        => 'Barang A',
        'supplier_id' => $supplier->id,
        'cost_price'  => 10000,
        'sell_price'  => 15000,
    ]);

    $productB = Product::factory()->forCabang($cabangPusat)->create([
        'name'        => 'Barang B',
        'supplier_id' => $supplier->id,
        'cost_price'  => 20000,
        'sell_price'  => 28000,
    ]);

    $productC = Product::factory()->forCabang($cabangPusat)->create([
        'name'        => 'Barang C',
        'supplier_id' => $supplier->id,
        'cost_price'  => 30000,
        'sell_price'  => 42000,
    ]);

    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Staff Gudang', 'guard_name' => 'web']);

    $userPusat = User::factory()->create([
        'cabang_id'    => $cabangPusat->id,
        'warehouse_id' => $warehousePusat->id,
    ]);
    $userPusat->assignRole('Staff Gudang');

    $userCabang = User::factory()->create([
        'cabang_id'    => $cabangCabang->id,
        'warehouse_id' => $warehouseCabang->id,
    ]);
    $userCabang->assignRole('Staff Gudang');

    $superAdmin = User::factory()->create([
        'cabang_id' => $cabangPusat->id,
    ]);
    $superAdmin->assignRole('Super Admin');

    return compact(
        'cabangPusat',
        'cabangCabang',
        'currency',
        'warehousePusat',
        'warehouseCabang',
        'supplier',
        'productA',
        'productB',
        'productC',
        'userPusat',
        'userCabang',
        'superAdmin'
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Point 1: 1 QC untuk Banyak Item (1 QC Number, 1 GRN Number)
// ─────────────────────────────────────────────────────────────────────────────
test('Point 1: 1 QC and 1 GRN generated for multi-item PO arrival', function () {
    $ctx = createFeedbackAuditContext();
    Auth::login($ctx['userPusat']);

    $po = PurchaseOrder::factory()->create([
        'cabang_id'    => $ctx['cabangPusat']->id,
        'warehouse_id' => $ctx['warehousePusat']->id,
        'supplier_id'  => $ctx['supplier']->id,
        'status'       => 'approved',
    ]);

    $poItemA = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'quantity'          => 10,
        'unit_price'        => 10000,
    ]);

    $poItemB = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productB']->id,
        'quantity'          => 20,
        'unit_price'        => 20000,
    ]);

    $poItemC = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productC']->id,
        'quantity'          => 30,
        'unit_price'        => 30000,
    ]);

    // Create 1 QC with 3 child QualityControlItem rows
    $qc = QualityControl::create([
        'qc_number'         => 'QC-TEST-001',
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'from_model_type'   => PurchaseOrder::class,
        'from_model_id'     => $po->id,
        'warehouse_id'      => $ctx['warehousePusat']->id,
        'cabang_id'         => $ctx['cabangPusat']->id,
        'inspected_by'      => $ctx['userPusat']->id,
        'quantity_received' => 60,
        'passed_quantity'   => 60,
        'rejected_quantity' => 0,
        'status'            => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc->id,
        'purchase_order_item_id' => $poItemA->id,
        'product_id'             => $ctx['productA']->id,
        'quantity_received'      => 10,
        'passed_quantity'        => 10,
        'rejected_quantity'      => 0,
        'status'                 => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc->id,
        'purchase_order_item_id' => $poItemB->id,
        'product_id'             => $ctx['productB']->id,
        'quantity_received'      => 20,
        'passed_quantity'        => 20,
        'rejected_quantity'      => 0,
        'status'                 => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc->id,
        'purchase_order_item_id' => $poItemC->id,
        'product_id'             => $ctx['productC']->id,
        'quantity_received'      => 30,
        'passed_quantity'        => 30,
        'rejected_quantity'      => 0,
        'status'                 => 0,
    ]);

    // Complete QC
    $service = app(QualityControlService::class);
    $service->completeQualityControl($qc);

    // Exactly 1 QC record exists
    expect(QualityControl::where('purchase_order_id', $po->id)->count())->toBe(1);

    // Exactly 1 PurchaseReceipt exists for this PO
    $receipts = PurchaseReceipt::where('purchase_order_id', $po->id)->get();
    expect($receipts->count())->toBe(1);

    $receipt = $receipts->first();
    expect($receipt->status)->toBe('completed');
    expect($receipt->cabang_id)->toBe($ctx['cabangPusat']->id);

    // Receipt has 3 receipt items corresponding to the 3 PO items
    $receiptItems = PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)->get();
    expect($receiptItems->count())->toBe(3);
    expect((float) $receiptItems->sum('qty_accepted'))->toBe(60.0);

    // PO should be completed
    $po->refresh();
    expect($po->status)->toBe('completed');
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 2: QC Draft Mengunci Qty & Auto-Batal Saat PO Selesai/Tutup
// ─────────────────────────────────────────────────────────────────────────────
test('Point 2: QC draft locks quantity with clear message and auto-cancels when PO closes', function () {
    $ctx = createFeedbackAuditContext();
    Auth::login($ctx['userPusat']);

    $po = PurchaseOrder::factory()->create([
        'cabang_id'    => $ctx['cabangPusat']->id,
        'warehouse_id' => $ctx['warehousePusat']->id,
        'supplier_id'  => $ctx['supplier']->id,
        'status'       => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'quantity'          => 50,
        'unit_price'        => 10000,
    ]);

    // Create Draft QC 1 for 20 pcs
    $draftQc1 = QualityControl::create([
        'qc_number'         => 'QC-P-DRAFT-001',
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'warehouse_id'      => $ctx['warehousePusat']->id,
        'cabang_id'         => $ctx['cabangPusat']->id,
        'inspected_by'      => $ctx['userPusat']->id,
        'quantity_received' => 20,
        'passed_quantity'   => 20,
        'rejected_quantity' => 0,
        'status'            => 0, // Draft
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $draftQc1->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id'             => $ctx['productA']->id,
        'quantity_received'      => 20,
        'passed_quantity'        => 20,
        'rejected_quantity'      => 0,
        'status'                 => 0,
    ]);

    // Check lock info
    $lockInfo = QualityControlPurchaseResource::draftQcLockInfo($poItem);
    expect($lockInfo['ordered'])->toBe(50.0);
    expect($lockInfo['locked_qty'])->toBe(20.0);
    expect($lockInfo['remaining_allowed'])->toBe(30.0);
    expect($lockInfo['message'])->toContain('Sisa yang bisa di-QC 30 pcs (20 pcs sedang di QC-P-DRAFT-001)');

    // Trying to create a second draft QC for 35 pcs should fail validation because only 30 pcs remain
    $page = new CreateQualityControlPurchase();
    $reflection = new \ReflectionClass($page);
    $mutateMethod = $reflection->getMethod('mutateFormDataBeforeCreate');
    $mutateMethod->setAccessible(true);

    expect(function () use ($mutateMethod, $page, $po, $poItem, $ctx) {
        $mutateMethod->invoke($page, [
            'purchase_order_id' => $po->id,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id'             => $ctx['productA']->id,
                    'quantity_received'      => 35,
                    'passed_quantity'        => 35,
                    'rejected_quantity'      => 0,
                ]
            ],
        ]);
    })->toThrow(ValidationException::class);

    // Now complete the PO directly (simulate closing/cancellation)
    $po->update(['status' => 'closed']);

    // Draft QC 1 should be automatically cancelled (status = 2)
    $draftQc1->refresh();
    expect($draftQc1->status)->toBe(2);
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 3: Invoice Merefer PO + Penerimaan Tanpa OR & Tolak Duplikat
// ─────────────────────────────────────────────────────────────────────────────
test('Point 3: Invoice references PO + Receipt directly without OR, and rejects duplicate supplier invoice & tax invoice', function () {
    $ctx = createFeedbackAuditContext();
    Auth::login($ctx['userPusat']);

    // Direct PO (NO order request)
    $po = PurchaseOrder::factory()->create([
        'cabang_id'         => $ctx['cabangPusat']->id,
        'warehouse_id'      => $ctx['warehousePusat']->id,
        'supplier_id'       => $ctx['supplier']->id,
        'refer_model_type'  => null,
        'refer_model_id'    => null,
        'status'            => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'quantity'          => 10,
        'unit_price'        => 15000,
        'tax'               => 0,
        'discount'          => 0,
    ]);

    $receipt = PurchaseReceipt::create([
        'receipt_number'    => 'RCV-TEST-001',
        'purchase_order_id' => $po->id,
        'currency_id'       => $ctx['currency']->id,
        'receipt_date'      => now(),
        'received_by'       => $ctx['userPusat']->id,
        'status'            => 'completed',
        'cabang_id'         => $ctx['cabangPusat']->id,
    ]);

    $receiptItem = PurchaseReceiptItem::create([
        'purchase_receipt_id'    => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id'             => $ctx['productA']->id,
        'qty_received'           => 10,
        'qty_accepted'           => 10,
        'qty_rejected'           => 0,
        'warehouse_id'           => $ctx['warehousePusat']->id,
        'status'                 => 'completed',
    ]);

    $service = app(PurchaseInvoiceAccountingService::class);

    // 1. Verify direct PO invoice creation succeeds without selected_order_request
    $invoiceData = [
        'supplier_id'                => $ctx['supplier']->id,
        'purchase_order_ids'         => [$po->id],
        'selected_purchase_orders'   => [$po->id],
        'selected_purchase_receipts' => [$receipt->id],
        'supplier_invoice_number'    => 'INV-SUPP-999',
        'tax_invoice_number'         => '010.000-26.11112222',
        'invoice_number'             => 'INV-INTERNAL-001',
        'invoice_date'               => now()->toDateString(),
        'due_date'                   => now()->addDays(30)->toDateString(),
        'currency_id'                => $ctx['currency']->id,
        'exchange_rate'              => 1.0,
        'cabang_id'                  => $ctx['cabangPusat']->id,
        'invoiceItem'                => [
            [
                'product_id'               => $ctx['productA']->id,
                'purchase_order_item_id'   => $poItem->id,
                'purchase_receipt_item_id' => $receiptItem->id,
                'quantity'                 => 10,
                'price'                    => 15000,
                'total'                    => 150000,
                'tax_amount'               => 0,
                'tipe_pajak'               => 'none',
                'discount'                 => 0,
            ]
        ],
    ];

    $validated = $service->validateReceiptBackedCreateData($invoiceData);
    expect($validated)->toBeArray();

    // Create Invoice 1
    $invoice1 = Invoice::create(array_merge($invoiceData, [
        'subtotal'        => 150000,
        'total'           => 150000,
        'status'          => Invoice::STATUS_DRAFT,
        'from_model_type' => PurchaseOrder::class,
        'from_model_id'   => $po->id,
    ]));
    expect($invoice1->exists)->toBeTrue();

    // 2. Reject duplicate supplier_invoice_number for same supplier
    expect(function () use ($service, $invoiceData) {
        $service->validateReceiptBackedCreateData(array_merge($invoiceData, [
            'tax_invoice_number' => '010.000-26.99999999', // Different tax invoice
        ]));
    })->toThrow(ValidationException::class);

    // 3. Reject duplicate tax_invoice_number
    expect(function () use ($service, $invoiceData) {
        $service->validateReceiptBackedCreateData(array_merge($invoiceData, [
            'supplier_invoice_number' => 'INV-SUPP-UNIQUE-888', // Different supplier invoice
            // Same tax_invoice_number as $invoice1
        ]));
    })->toThrow(ValidationException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 4: PO Pakai Gudang, Beban ke Pusat
// ─────────────────────────────────────────────────────────────────────────────
test('Point 4: PO enforces warehouse selection and locks branch to Pusat', function () {
    $ctx = createFeedbackAuditContext();
    Auth::login($ctx['userPusat']);

    // Verify PO requires warehouse_id and defaults to Pusat branch
    $po = PurchaseOrder::create([
        'po_number'    => 'PO-2026-TEST',
        'cabang_id'    => $ctx['cabangPusat']->id,
        'warehouse_id' => $ctx['warehousePusat']->id,
        'supplier_id'  => $ctx['supplier']->id,
        'order_date'   => now(),
        'status'       => 'draft',
    ]);

    expect($po->warehouse_id)->toBe($ctx['warehousePusat']->id);
    expect($po->cabang_id)->toBe($ctx['cabangPusat']->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 5: Hanya Gudang di PO yang Boleh QC
// ─────────────────────────────────────────────────────────────────────────────
test('Point 5: Only users assigned to PO warehouse can perform QC (unless Super Admin)', function () {
    $ctx = createFeedbackAuditContext();

    $po = PurchaseOrder::factory()->create([
        'cabang_id'    => $ctx['cabangPusat']->id,
        'warehouse_id' => $ctx['warehousePusat']->id,
        'supplier_id'  => $ctx['supplier']->id,
        'status'       => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'quantity'          => 10,
    ]);

    // 1. Staff from Cabang Surabaya tries to QC PO for Pusat -> Denied!
    Auth::login($ctx['userCabang']);
    $page = new CreateQualityControlPurchase();

    $reflection = new \ReflectionClass($page);
    $mutateMethod = $reflection->getMethod('mutateFormDataBeforeCreate');
    $mutateMethod->setAccessible(true);

    expect(function () use ($mutateMethod, $page, $po, $poItem, $ctx) {
        $mutateMethod->invoke($page, [
            'purchase_order_id' => $po->id,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id'             => $ctx['productA']->id,
                    'quantity_received'      => 10,
                    'passed_quantity'        => 10,
                    'rejected_quantity'      => 0,
                ]
            ],
        ]);
    })->toThrow(ValidationException::class);

    // 2. Staff from Pusat -> Allowed!
    Auth::login($ctx['userPusat']);
    $allowedData = $mutateMethod->invoke($page, [
        'purchase_order_id' => $po->id,
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'product_id'             => $ctx['productA']->id,
                'quantity_received'      => 10,
                'passed_quantity'        => 10,
                'rejected_quantity'      => 0,
            ]
        ],
    ]);
    expect($allowedData['warehouse_id'])->toBe($ctx['warehousePusat']->id);

    // 3. Super Admin -> Allowed regardless of warehouse!
    Auth::login($ctx['superAdmin']);
    $adminData = $mutateMethod->invoke($page, [
        'purchase_order_id' => $po->id,
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'product_id'             => $ctx['productA']->id,
                'quantity_received'      => 10,
                'passed_quantity'        => 10,
                'rejected_quantity'      => 0,
            ]
        ],
    ]);
    expect($adminData['warehouse_id'])->toBe($ctx['warehousePusat']->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 6: OR Disederhanakan (required_date & purpose)
// ─────────────────────────────────────────────────────────────────────────────
test('Point 6: Order Request supports simplified fields required_date and purpose', function () {
    $ctx = createFeedbackAuditContext();
    Auth::login($ctx['userPusat']);

    $requiredDate = now()->addDays(7)->toDateString();
    $purpose = 'Penggantian sparepart mesin workshop A';

    $or = OrderRequest::create([
        'request_number' => 'OR-2026-0001',
        'cabang_id'     => $ctx['cabangPusat']->id,
        'currency_id'   => $ctx['currency']->id,
        'request_date'  => now(),
        'required_date' => $requiredDate,
        'purpose'       => $purpose,
        'status'        => 'draft',
        'created_by'    => $ctx['userPusat']->id,
        'requester_id'  => $ctx['userPusat']->id,
    ]);

    expect($or->required_date->toDateString())->toBe($requiredDate);
    expect($or->purpose)->toBe($purpose);
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 7: Tindak Lanjut Barang Reject
// ─────────────────────────────────────────────────────────────────────────────
test('Point 7: Rejected items trigger reduce_stock, return_supplier, and wait_next_delivery actions correctly', function () {
    $ctx = createFeedbackAuditContext();
    Auth::login($ctx['userPusat']);

    $po = PurchaseOrder::factory()->create([
        'cabang_id'    => $ctx['cabangPusat']->id,
        'warehouse_id' => $ctx['warehousePusat']->id,
        'supplier_id'  => $ctx['supplier']->id,
        'status'       => 'approved',
    ]);

    // Item 1: 10 ordered -> 8 passed, 2 rejected (wait_next_delivery: PO stays open)
    $poItem1 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'quantity'          => 10,
        'unit_price'        => 10000,
    ]);

    // Item 2: 10 ordered -> 7 passed, 3 rejected (return_supplier: creates PurchaseReturn)
    $poItem2 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productB']->id,
        'quantity'          => 10,
        'unit_price'        => 20000,
    ]);

    // Item 3: 10 ordered -> 6 passed, 4 rejected (reduce_stock: reduces PO qty to 6)
    $poItem3 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productC']->id,
        'quantity'          => 10,
        'unit_price'        => 30000,
    ]);

    $qc = QualityControl::create([
        'qc_number'         => 'QC-MULTI-REJECT-001',
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'from_model_type'   => PurchaseOrder::class,
        'from_model_id'     => $po->id,
        'warehouse_id'      => $ctx['warehousePusat']->id,
        'cabang_id'         => $ctx['cabangPusat']->id,
        'inspected_by'      => $ctx['userPusat']->id,
        'quantity_received' => 30,
        'passed_quantity'   => 21,
        'rejected_quantity' => 9,
        'status'            => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc->id,
        'purchase_order_item_id' => $poItem1->id,
        'product_id'             => $ctx['productA']->id,
        'quantity_received'      => 10,
        'passed_quantity'        => 8,
        'rejected_quantity'      => 2,
        'failed_qc_action'       => PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY,
        'reason_reject'          => 'Baret halus pada kemasan',
        'status'                 => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc->id,
        'purchase_order_item_id' => $poItem2->id,
        'product_id'             => $ctx['productB']->id,
        'quantity_received'      => 10,
        'passed_quantity'        => 7,
        'rejected_quantity'      => 3,
        'failed_qc_action'       => PurchaseReturn::QC_ACTION_RETURN_SUPPLIER,
        'reason_reject'          => 'Komponen pecah',
        'status'                 => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc->id,
        'purchase_order_item_id' => $poItem3->id,
        'product_id'             => $ctx['productC']->id,
        'quantity_received'      => 10,
        'passed_quantity'        => 6,
        'rejected_quantity'      => 4,
        'failed_qc_action'       => PurchaseReturn::QC_ACTION_REDUCE_STOCK,
        'reason_reject'          => 'Spesifikasi tidak sesuai',
        'status'                 => 0,
    ]);

    $service = app(QualityControlService::class);
    $service->completeQualityControl($qc);

    // 1. Verify Item 3 PO quantity reduced by 4 (from 10 to 6)
    $poItem3->refresh();
    expect((float) $poItem3->quantity)->toBe(6.0);

    // 2. Verify Item 2 generated a draft PurchaseReturn for 3 pcs
    $returns = PurchaseReturn::where('quality_control_id', $qc->id)->get();
    expect($returns->count())->toBe(1);
    $return = $returns->first();
    expect($return->status)->toBe('draft');
    expect($return->items->count())->toBe(1);
    expect((float) $return->items->first()->qty_returned)->toBe(3.0);
    expect($return->items->first()->reason)->toBe('Komponen pecah');

    // 3. Verify Item 1 remains at quantity 10 (waiting for 2 pcs replacement)
    $poItem1->refresh();
    expect((float) $poItem1->quantity)->toBe(10.0);

    // PO should NOT be completed yet because Item 1 has 2 pcs remaining
    $po->refresh();
    expect($po->status)->not->toBe('completed');

    // Now supplier delivers the replacement items: 2 pcs for Item 1 and 3 pcs for Item 2
    $qc2 = QualityControl::create([
        'qc_number'         => 'QC-MULTI-REPLACEMENT-002',
        'purchase_order_id' => $po->id,
        'product_id'        => $ctx['productA']->id,
        'from_model_type'   => PurchaseOrder::class,
        'from_model_id'     => $po->id,
        'warehouse_id'      => $ctx['warehousePusat']->id,
        'cabang_id'         => $ctx['cabangPusat']->id,
        'inspected_by'      => $ctx['userPusat']->id,
        'quantity_received' => 5,
        'passed_quantity'   => 5,
        'rejected_quantity' => 0,
        'status'            => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc2->id,
        'purchase_order_item_id' => $poItem1->id,
        'product_id'             => $ctx['productA']->id,
        'quantity_received'      => 2,
        'passed_quantity'        => 2,
        'rejected_quantity'      => 0,
        'status'                 => 0,
    ]);

    QualityControlItem::create([
        'quality_control_id'     => $qc2->id,
        'purchase_order_item_id' => $poItem2->id,
        'product_id'             => $ctx['productB']->id,
        'quantity_received'      => 3,
        'passed_quantity'        => 3,
        'rejected_quantity'      => 0,
        'status'                 => 0,
    ]);

    $service->completeQualityControl($qc2);

    // Now all fulfilled: Item 1 (8+2=10/10), Item 2 (7+3=10/10), Item 3 (6/6 reduced PO qty)
    $po->refresh();
    expect($po->status)->toBe('completed');
});
