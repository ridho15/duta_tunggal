<?php

use App\Filament\Resources\QualityControlPurchaseResource\Pages\CreateQualityControlPurchase;
use App\Filament\Resources\VendorPaymentResource\Pages\CreateVendorPayment;
use App\Helpers\MoneyHelper;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\QualityControl;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\Warehouse;
use App\Services\ApprovalControlService;
use App\Services\LedgerPostingService;
use App\Services\OrderRequestService;
use App\Services\PurchaseInvoiceAccountingService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Setup Helpers
// ─────────────────────────────────────────────────────────────────────────────

function createRoleAndAssign(User $user, string $roleName, array $permissions = []): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    foreach ($permissions as $permName) {
        $perm = Permission::firstOrCreate([
            'name' => $permName,
            'guard_name' => 'web',
        ]);
        if (! $role->hasPermissionTo($permName)) {
            $role->givePermissionTo($perm);
        }
    }

    $user->assignRole($role);
}

function createBaseControlContext(): array
{
    $cabang = Cabang::factory()->create(['nama' => 'Cabang Audit Pusat']);
    UnitOfMeasure::factory()->create();
    $currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'status' => 1,
    ]);

    $supplier = Supplier::factory()->create([
        'cabang_id' => $cabang->id,
        'tempo_hutang' => 30,
    ]);

    $product = Product::factory()->forCabang($cabang)->create([
        'supplier_id' => $supplier->id,
        'cost_price' => 50000,
        'sell_price' => 75000,
    ]);

    return compact('cabang', 'currency', 'warehouse', 'supplier', 'product');
}

function createReceiptBackedInvoiceContext(array $baseContext, ?Supplier $customSupplier = null): array
{
    $supplier = $customSupplier ?? $baseContext['supplier'];

    $product = $baseContext['product'];
    if ($customSupplier) {
        $product = Product::factory()->forCabang($baseContext['cabang'])->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50000,
            'sell_price' => 75000,
        ]);
    }

    $orderRequest = OrderRequest::factory()->create([
        'status' => 'approved',
        'cabang_id' => $baseContext['cabang']->id,
        'currency_id' => $baseContext['currency']->id,
    ]);

    $orItem = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'cabang_id' => $baseContext['cabang']->id,
        'quantity' => 10,
        'fulfilled_quantity' => 0,
        'status' => OrderRequestItem::STATUS_APPROVED,
        'currency_id' => $baseContext['currency']->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'cabang_id' => $baseContext['cabang']->id,
        'status' => 'completed',
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orderRequest->id,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000000,
        'currency_id' => $baseContext['currency']->id,
        'refer_item_model_type' => OrderRequestItem::class,
        'refer_item_model_id' => $orItem->id,
    ]);

    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'cabang_id' => $baseContext['cabang']->id,
        'status' => 'completed',
    ]);

    $receiptItem = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'qty_received' => 1,
        'qty_accepted' => 1,
        'qty_rejected' => 0,
        'warehouse_id' => $baseContext['warehouse']->id,
    ]);

    return compact('orderRequest', 'orItem', 'purchaseOrder', 'poItem', 'receipt', 'receiptItem', 'supplier', 'product');
}

// ─────────────────────────────────────────────────────────────────────────────
// Point 1: Segregation of Duties & Tiered Approval Thresholds
// ─────────────────────────────────────────────────────────────────────────────

describe('Poin 1: Anti-Self-Approval & Tiered Approval Thresholds', function () {

    it('prevents creator from approving their own Order Request unless Top Tier/Owner', function () {
        $context = createBaseControlContext();
        $approvalService = app(ApprovalControlService::class);

        $creatorUser = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($creatorUser, 'Purchasing Manager', ['approve order request']);

        $independentManager = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($independentManager, 'Purchasing Manager', ['approve order request']);

        $orderRequest = OrderRequest::factory()->create([
            'created_by' => $creatorUser->id,
            'status' => 'request_approve',
            'cabang_id' => $context['cabang']->id,
        ]);

        OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $context['product']->id,
            'quantity' => 10,
            'unit_price' => 500000, // Total = Rp 5.000.000 (Tier 1)
            'currency_id' => $context['currency']->id,
        ]);

        // Creator attempts to approve own OR -> Forbidden
        $creatorCheck = $approvalService->canApproveOrderRequest($creatorUser, $orderRequest);
        expect($creatorCheck['allowed'])->toBeFalse()
            ->and($creatorCheck['reason'])->toContain('Pemisahan tugas (Segregation of Duties)');

        // Independent Purchasing Manager attempts to approve -> Allowed for Tier 1
        $independentCheck = $approvalService->canApproveOrderRequest($independentManager, $orderRequest);
        expect($independentCheck['allowed'])->toBeTrue()
            ->and($independentCheck['reason'])->toBeNull();
    });

    it('requires Top Tier roles for Order Request exceeding Rp 10.000.000', function () {
        $context = createBaseControlContext();
        $approvalService = app(ApprovalControlService::class);

        $creatorUser = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        $purchasingManager = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($purchasingManager, 'Purchasing Manager', ['approve order request']);

        $financeManager = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($financeManager, 'Finance Manager', ['approve order request']);

        $orderRequest = OrderRequest::factory()->create([
            'created_by' => $creatorUser->id,
            'status' => 'request_approve',
            'cabang_id' => $context['cabang']->id,
        ]);

        // High value: 25 unit x Rp 1.000.000 = Rp 25.000.000 (> Tier 1 limit of Rp 10M)
        OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $context['product']->id,
            'quantity' => 25,
            'unit_price' => 1000000,
            'currency_id' => $context['currency']->id,
        ]);

        // Purchasing Manager cannot approve Tier 2 (> 10M)
        $pmCheck = $approvalService->canApproveOrderRequest($purchasingManager, $orderRequest);
        expect($pmCheck['allowed'])->toBeFalse()
            ->and($pmCheck['reason'])->toContain('Persetujuan bertingkat');

        // Finance Manager (Top Tier) can approve
        $fmCheck = $approvalService->canApproveOrderRequest($financeManager, $orderRequest);
        expect($fmCheck['allowed'])->toBeTrue();
    });

    it('prevents creator from approving their own Sales Order and enforces tiered limits', function () {
        $context = createBaseControlContext();
        $approvalService = app(ApprovalControlService::class);

        $salesUser = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($salesUser, 'Sales Manager', ['response sales order']);

        $otherSalesManager = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($otherSalesManager, 'Sales Manager', ['response sales order']);

        $director = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($director, 'Super Admin', ['response sales order']);

        $saleOrderTier1 = SaleOrder::factory()->create([
            'created_by' => $salesUser->id,
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
            'total_amount' => 8000000, // Rp 8.000.000 (<= 10M)
        ]);

        // Creator cannot approve own SO
        $selfCheck = $approvalService->canApproveSaleOrder($salesUser, $saleOrderTier1);
        expect($selfCheck['allowed'])->toBeFalse()
            ->and($selfCheck['reason'])->toContain('Pemisahan tugas');

        // Other Sales Manager can approve Tier 1 SO
        $otherCheck = $approvalService->canApproveSaleOrder($otherSalesManager, $saleOrderTier1);
        expect($otherCheck['allowed'])->toBeTrue();

        // High value SO: Rp 50.000.000 (> 10M)
        $saleOrderTier2 = SaleOrder::factory()->create([
            'created_by' => $salesUser->id,
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
            'total_amount' => 50000000,
        ]);

        // Regular Sales Manager cannot approve Tier 2
        $pmTier2Check = $approvalService->canApproveSaleOrder($otherSalesManager, $saleOrderTier2);
        expect($pmTier2Check['allowed'])->toBeFalse()
            ->and($pmTier2Check['reason'])->toContain('Persetujuan bertingkat');

        // Director (Super Admin) can approve
        $directorCheck = $approvalService->canApproveSaleOrder($director, $saleOrderTier2);
        expect($directorCheck['allowed'])->toBeTrue();
    });

    it('enforces anti-self-approval and tiered limits on Payment Request', function () {
        $context = createBaseControlContext();
        $approvalService = app(ApprovalControlService::class);

        $requester = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($requester, 'Accounting', []);

        $accountingApprover = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($accountingApprover, 'Accounting', []);

        $financeManager = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($financeManager, 'Finance Manager', []);

        $paymentRequest = PaymentRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
            'total_amount' => 15000000, // Rp 15.000.000 (> 10M)
        ]);

        // Requester cannot approve own payment request
        $selfCheck = $approvalService->canApprovePaymentRequest($requester, $paymentRequest);
        expect($selfCheck['allowed'])->toBeFalse()
            ->and($selfCheck['reason'])->toContain('Pemisahan tugas');

        // Accounting cannot approve Tier 2 (> 10M)
        $tier2Check = $approvalService->canApprovePaymentRequest($accountingApprover, $paymentRequest);
        expect($tier2Check['allowed'])->toBeFalse()
            ->and($tier2Check['reason'])->toContain('Persetujuan bertingkat');

        // Finance Manager can approve Tier 2
        $fmCheck = $approvalService->canApprovePaymentRequest($financeManager, $paymentRequest);
        expect($fmCheck['allowed'])->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 2: Purchase Order Price Deviation Controls
// ─────────────────────────────────────────────────────────────────────────────

describe('Poin 2: PO Price Deviation Controls', function () {

    it('auto-approves PO when item price exactly matches Order Request price', function () {
        $context = createBaseControlContext();
        $user = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($user, 'Super Admin', ['approve order request', 'view purchase order']);
        Auth::login($user);

        $orderRequest = OrderRequest::factory()->create([
            'created_by' => $user->id,
            'status' => 'request_approve',
            'cabang_id' => $context['cabang']->id,
            'currency_id' => $context['currency']->id,
        ]);

        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $context['product']->id,
            'supplier_id' => $context['supplier']->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'original_price' => 50000,
            'status' => OrderRequestItem::STATUS_APPROVED,
            'currency_id' => $context['currency']->id,
        ]);

        $po = app(OrderRequestService::class)->createPurchaseOrder($orderRequest, [
            'po_number' => 'PO-AUDIT-MATCH-001',
            'supplier_id' => $context['supplier']->id,
            'order_date' => Carbon::now()->toDateTimeString(),
            'selected_items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'unit_price' => 50000, // Identical price
                    'include' => true,
                ],
            ],
        ]);

        expect($po->status)->toBe('approved')
            ->and((float) $po->purchaseOrderItem->first()->original_unit_price)->toEqual(50000.0)
            ->and((float) $po->purchaseOrderItem->first()->unit_price)->toEqual(50000.0);
    });

    it('sets PO to request_approval and records reason when price deviates from Order Request', function () {
        $context = createBaseControlContext();
        $user = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($user, 'Super Admin', ['approve order request', 'view purchase order']);
        Auth::login($user);

        $orderRequest = OrderRequest::factory()->create([
            'created_by' => $user->id,
            'status' => 'request_approve',
            'cabang_id' => $context['cabang']->id,
            'currency_id' => $context['currency']->id,
        ]);

        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $context['product']->id,
            'supplier_id' => $context['supplier']->id,
            'quantity' => 10,
            'unit_price' => 50000, // OR unit price = Rp 50.000
            'original_price' => 50000,
            'status' => OrderRequestItem::STATUS_APPROVED,
            'currency_id' => $context['currency']->id,
        ]);

        // Change price to 58.000 with explanation
        $po = app(OrderRequestService::class)->createPurchaseOrder($orderRequest, [
            'po_number' => 'PO-AUDIT-DEV-001',
            'supplier_id' => $context['supplier']->id,
            'order_date' => Carbon::now()->toDateTimeString(),
            'selected_items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'unit_price' => 58000, // Price modified from 50.000 to 58.000
                    'price_change_reason' => 'Kenaikan harga bahan baku dari supplier per September 2026',
                    'include' => true,
                ],
            ],
        ]);

        expect($po->status)->toBe('request_approval')
            ->and($po->note)->toContain('PO memerlukan persetujuan harga')
            ->and((float) $po->purchaseOrderItem->first()->original_unit_price)->toEqual(50000.0)
            ->and((float) $po->purchaseOrderItem->first()->unit_price)->toEqual(58000.0)
            ->and($po->purchaseOrderItem->first()->price_change_reason)->toBe('Kenaikan harga bahan baku dari supplier per September 2026');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 3: Strict Locking of QC Inspector
// ─────────────────────────────────────────────────────────────────────────────

describe('Poin 3: Strict QC Inspector Lock to Auth::id()', function () {

    it('strictly locks inspected_by to authenticated user regardless of input payload', function () {
        $context = createBaseControlContext();
        $inspectorUser = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        $spoofedUser = User::factory()->create(['cabang_id' => $context['cabang']->id]);

        createRoleAndAssign($inspectorUser, 'Super Admin', [
            'view any quality control',
            'view quality control',
            'create quality control',
            'create quality control purchase',
            'update quality control',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $context['supplier']->id,
            'cabang_id' => $context['cabang']->id,
            'status' => 'approved',
            'created_by' => $inspectorUser->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $context['product']->id,
            'quantity' => 15,
            'unit_price' => 50000,
            'currency_id' => $context['currency']->id,
        ]);

        Livewire::actingAs($inspectorUser)
            ->test(CreateQualityControlPurchase::class)
            ->assertFormSet([
                'inspected_by' => $inspectorUser->id,
            ])
            ->fillForm([
                'from_model_id' => $poItem->id,
                'qc_number' => 'QC-TEST-LOCK-001',
                'product_id' => $context['product']->id,
                'warehouse_id' => $context['warehouse']->id,
                'quantity_received' => 15,
                'passed_quantity' => 15,
                'rejected_quantity' => 0,
                'inspected_by' => $spoofedUser->id, // Attempt to spoof inspector
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $qc = QualityControl::where('qc_number', 'QC-TEST-LOCK-001')->first();
        expect($qc)->not->toBeNull()
            ->and($qc->inspected_by)->toBe($inspectorUser->id);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 4: Supplier Invoice Number & Tax Invoice Number + Duplicate Prevention
// ─────────────────────────────────────────────────────────────────────────────

describe('Poin 4: Supplier Invoice Number & Tax Invoice Number Duplicate Prevention', function () {

    it('saves supplier_invoice_number and tax_invoice_number on invoice', function () {
        $context = createBaseControlContext();
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'supplier_invoice_number' => 'SUPP-INV-9988',
            'tax_invoice_number' => '010.001-26.12345678',
            'supplier_id' => $context['supplier']->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => 1,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
            'currency_id' => $context['currency']->id,
            'nominal' => 1,
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        expect($invoice->supplier_invoice_number)->toBe('SUPP-INV-9988')
            ->and($invoice->tax_invoice_number)->toBe('010.001-26.12345678')
            ->and($invoice->supplier_id)->toBe($context['supplier']->id);
    });

    it('rejects duplicate supplier_invoice_number for the same supplier', function () {
        $context = createBaseControlContext();
        $receiptCtx = createReceiptBackedInvoiceContext($context);
        $service = app(PurchaseInvoiceAccountingService::class);

        // Pre-existing invoice for this supplier
        Invoice::create([
            'invoice_number' => 'INV-FIRST-001',
            'supplier_invoice_number' => 'INV-VENDOR-ABC-123',
            'tax_invoice_number' => '010.001-26.11111111',
            'supplier_id' => $context['supplier']->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $receiptCtx['purchaseOrder']->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
            'currency_id' => $context['currency']->id,
            'nominal' => 1,
            'subtotal' => 500000,
            'total' => 500000,
        ]);

        // Submitting same supplier invoice number for same supplier must throw ValidationException
        expect(function () use ($service, $receiptCtx, $context) {
            $service->validateReceiptBackedCreateData([
                'purchase_order_ids' => [$receiptCtx['purchaseOrder']->id],
                'selected_purchase_receipts' => [$receiptCtx['receipt']->id],
                'selected_supplier' => $context['supplier']->id,
                'selected_order_request' => $receiptCtx['orderRequest']->id,
                'supplier_id' => $context['supplier']->id,
                'supplier_invoice_number' => 'INV-VENDOR-ABC-123', // Duplicate!
                'tax_invoice_number' => '010.001-26.22222222',
                'cabang_id' => $context['cabang']->id,
                'invoiceItem' => [],
            ]);
        })->toThrow(ValidationException::class);
    });

    it('allows identical supplier_invoice_number when suppliers are different', function () {
        $context = createBaseControlContext();
        $service = app(PurchaseInvoiceAccountingService::class);

        $otherSupplier = Supplier::factory()->create([
            'cabang_id' => $context['cabang']->id,
            'perusahaan' => 'CV Supplier Kedua',
        ]);

        $receiptCtxB = createReceiptBackedInvoiceContext($context, $otherSupplier);

        // Pre-existing invoice for Supplier A
        Invoice::create([
            'invoice_number' => 'INV-SUPP-A',
            'supplier_invoice_number' => 'SHARED-INV-NUMBER-001',
            'supplier_id' => $context['supplier']->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => 1,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
            'currency_id' => $context['currency']->id,
            'nominal' => 1,
            'subtotal' => 200000,
            'total' => 200000,
        ]);

        // Same supplier invoice number for Supplier B is allowed
        $validated = $service->validateReceiptBackedCreateData([
            'purchase_order_ids' => [$receiptCtxB['purchaseOrder']->id],
            'selected_purchase_receipts' => [$receiptCtxB['receipt']->id],
            'selected_supplier' => $otherSupplier->id,
            'selected_order_request' => $receiptCtxB['orderRequest']->id,
            'supplier_id' => $otherSupplier->id,
            'supplier_invoice_number' => 'SHARED-INV-NUMBER-001',
            'tax_invoice_number' => '010.001-26.33333333',
            'cabang_id' => $context['cabang']->id,
            'invoiceItem' => [],
        ]);

        expect($validated['supplier_invoice_number'])->toBe('SHARED-INV-NUMBER-001')
            ->and($validated['supplier_id'])->toBe($otherSupplier->id);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 5: 3-Way Match Price Tolerance & COA 5160 Variance Posting
// ─────────────────────────────────────────────────────────────────────────────

describe('Poin 5: 3-Way Match Price Tolerance & Accounting Posting', function () {

    it('accepts invoice price variance within tolerance (<= 1% or <= Rp 10.000)', function () {
        $context = createBaseControlContext();
        $receiptCtx = createReceiptBackedInvoiceContext($context);
        $service = app(PurchaseInvoiceAccountingService::class);

        // PO price: Rp 1.000.000. Invoice price: Rp 1.005.000 (variance: Rp 5.000, within Rp 10.000 tolerance)
        $validated = $service->validateReceiptBackedCreateData([
            'purchase_order_ids' => [$receiptCtx['purchaseOrder']->id],
            'selected_purchase_receipts' => [$receiptCtx['receipt']->id],
            'selected_supplier' => $context['supplier']->id,
            'selected_order_request' => $receiptCtx['orderRequest']->id,
            'supplier_id' => $context['supplier']->id,
            'supplier_invoice_number' => 'INV-TOL-001',
            'tax_invoice_number' => '010.001-26.44444444',
            'cabang_id' => $context['cabang']->id,
            'invoiceItem' => [
                [
                    'product_id' => $context['product']->id,
                    'quantity' => 1,
                    'po_price' => 1000000,
                    'price' => 1005000,
                ],
            ],
        ]);

        expect($validated)->toBeArray()
            ->and($validated['supplier_invoice_number'])->toBe('INV-TOL-001');
    });

    it('rejects invoice price variance exceeding tolerance (> 1% AND > Rp 10.000)', function () {
        $context = createBaseControlContext();
        $receiptCtx = createReceiptBackedInvoiceContext($context);
        $service = app(PurchaseInvoiceAccountingService::class);

        // PO line total: Rp 1.000.000. Invoice: Rp 1.050.000 (variance: Rp 50.000, which exceeds 1% / Rp 10.000)
        expect(function () use ($service, $receiptCtx, $context) {
            $service->validateReceiptBackedCreateData([
                'purchase_order_ids' => [$receiptCtx['purchaseOrder']->id],
                'selected_purchase_receipts' => [$receiptCtx['receipt']->id],
                'selected_supplier' => $context['supplier']->id,
                'selected_order_request' => $receiptCtx['orderRequest']->id,
                'supplier_id' => $context['supplier']->id,
                'supplier_invoice_number' => 'INV-EXCEED-001',
                'tax_invoice_number' => '010.001-26.55555555',
                'cabang_id' => $context['cabang']->id,
                'invoiceItem' => [
                    [
                        'product_id' => $context['product']->id,
                        'quantity' => 1,
                        'po_price' => 1000000,
                        'price' => 1050000, // 5% variance = Rp 50.000
                    ],
                ],
            ]);
        })->toThrow(ValidationException::class);
    });

    it('posts variance to Selisih Pembelian (COA 5160) during ledger posting', function () {
        $context = createBaseControlContext();

        // Ensure COAs exist
        $unbilledCoa = ChartOfAccount::firstOrCreate(
            ['code' => config('coa.unbilled_purchases', '2120')],
            ['name' => 'Hutang Belum Difakturkan', 'type' => 'Liability', 'status' => 1]
        );
        $apCoa = ChartOfAccount::firstOrCreate(
            ['code' => config('coa.accounts_payable', '2110')],
            ['name' => 'Hutang Usaha', 'type' => 'Liability', 'status' => 1]
        );
        $varianceCoa = ChartOfAccount::firstOrCreate(
            ['code' => config('coa.purchase_price_variance', '5160')],
            ['name' => 'Selisih Pembelian', 'type' => 'Expense', 'status' => 1]
        );

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $context['supplier']->id,
            'cabang_id' => $context['cabang']->id,
            'status' => 'approved',
        ]);

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'cabang_id' => $context['cabang']->id,
            'status' => 'completed',
        ]);

        // Invoice with subtotal 105.000 and variance +5.000 (PO subtotal was 100.000)
        $invoice = Invoice::create([
            'invoice_number' => 'INV-VAR-JOURNAL-001',
            'supplier_invoice_number' => 'SUPP-VAR-001',
            'tax_invoice_number' => '010.001-26.66666666',
            'supplier_id' => $context['supplier']->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'purchase_receipts' => [$receipt->id],
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
            'currency_id' => $context['currency']->id,
            'nominal' => 1,
            'subtotal' => 105000,
            'price_variance_amount' => 5000, // Rp 5.000 variance
            'total' => 105000,
            'tax' => 0,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $entries = app(LedgerPostingService::class)->postInvoice($invoice);

        $varianceEntry = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('coa_id', $varianceCoa->id)
            ->first();

        expect($varianceEntry)->not->toBeNull()
            ->and((float) $varianceEntry->debit)->toEqual(5000.0)
            ->and((float) $varianceEntry->credit)->toEqual(0.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 6: QC Reject Follow-Up Actions
// ─────────────────────────────────────────────────────────────────────────────

describe('Poin 6: QC Reject Follow-Up Actions', function () {

    it('creates draft PurchaseReturn with return_supplier action and resolves cleanly', function () {
        $context = createBaseControlContext();
        $user = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($user, 'Super Admin', []);
        Auth::login($user);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $context['supplier']->id,
            'cabang_id' => $context['cabang']->id,
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $context['product']->id,
            'quantity' => 20,
            'unit_price' => 50000,
            'currency_id' => $context['currency']->id,
        ]);

        $qc = QualityControl::create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'qc_number' => 'QC-REJECT-001',
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id,
            'quantity_received' => 20,
            'passed_quantity' => 15,
            'rejected_quantity' => 5,
            'reason_reject' => 'Barang rusak kemasan pecah',
            'status' => 0,
            'inspected_by' => $user->id,
            'cabang_id' => $context['cabang']->id,
        ]);

        $returnService = app(PurchaseReturnService::class);

        // Execute create from QC with 'return_supplier'
        $purchaseReturn = $returnService->createFromQualityControl($qc, PurchaseReturn::QC_ACTION_RETURN_SUPPLIER);

        expect($purchaseReturn)->not->toBeNull()
            ->and($purchaseReturn->failed_qc_action)->toBe(PurchaseReturn::QC_ACTION_RETURN_SUPPLIER)
            ->and($purchaseReturn->status)->toBe('draft')
            ->and($purchaseReturn->purchaseReturnItem->count())->toBe(1)
            ->and((float) $purchaseReturn->purchaseReturnItem->first()->qty_returned)->toEqual(5.0);

        // Execute resolution
        $returnService->executeQcResolution($purchaseReturn);

        $freshReturn = $purchaseReturn->fresh();
        expect($freshReturn->supplier_response)->toBe('return_to_supplier')
            ->and($freshReturn->tracking_notes)->toContain('Barang ditolak sebanyak 5 unit untuk dikembalikan ke supplier');
    });

    it('resolves reduce_stock by decrementing PurchaseOrderItem quantity', function () {
        $context = createBaseControlContext();
        $user = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        Auth::login($user);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $context['supplier']->id,
            'cabang_id' => $context['cabang']->id,
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $context['product']->id,
            'quantity' => 20,
            'unit_price' => 50000,
            'currency_id' => $context['currency']->id,
        ]);

        $qc = QualityControl::create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'qc_number' => 'QC-REDUCE-001',
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id,
            'quantity_received' => 20,
            'passed_quantity' => 15,
            'rejected_quantity' => 5,
            'status' => 0,
            'inspected_by' => $user->id,
            'cabang_id' => $context['cabang']->id,
        ]);

        $returnService = app(PurchaseReturnService::class);
        $purchaseReturn = $returnService->createFromQualityControl($qc, PurchaseReturn::QC_ACTION_REDUCE_STOCK);
        $returnService->executeQcResolution($purchaseReturn);

        // PO item quantity decremented from 20 to 15 (20 - 5)
        expect((float) $poItem->fresh()->quantity)->toEqual(15.0);
    });

    it('resolves wait_next_delivery by keeping PurchaseOrderItem quantity intact', function () {
        $context = createBaseControlContext();
        $user = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        Auth::login($user);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $context['supplier']->id,
            'cabang_id' => $context['cabang']->id,
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $context['product']->id,
            'quantity' => 20,
            'unit_price' => 50000,
            'currency_id' => $context['currency']->id,
        ]);

        $qc = QualityControl::create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'qc_number' => 'QC-WAIT-001',
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id,
            'quantity_received' => 20,
            'passed_quantity' => 15,
            'rejected_quantity' => 5,
            'status' => 0,
            'inspected_by' => $user->id,
            'cabang_id' => $context['cabang']->id,
        ]);

        $returnService = app(PurchaseReturnService::class);
        $purchaseReturn = $returnService->createFromQualityControl($qc, PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY);
        $returnService->executeQcResolution($purchaseReturn);

        // PO item quantity remains 20 to accept replacement shipment
        expect((float) $poItem->fresh()->quantity)->toEqual(20.0)
            ->and($purchaseReturn->fresh()->tracking_notes)->toContain('Waiting for supplier to resend');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Point 7: Vendor Payment Bank Transfer Fields & Proof File
// ─────────────────────────────────────────────────────────────────────────────

describe('Poin 7: Vendor Payment Transfer Validation & Proof File', function () {

    it('has proof_file column and supports fillable attribute on VendorPayment', function () {
        expect(Schema::hasColumn('vendor_payments', 'proof_file'))->toBeTrue();

        $context = createBaseControlContext();
        $payment = VendorPayment::create([
            'payment_number' => 'PAY-TEST-001',
            'supplier_id' => $context['supplier']->id,
            'payment_date' => now(),
            'payment_method' => 'Bank Transfer',
            'target_bank_account' => 'BCA 1234567890 a.n PT Supplier Utama',
            'transfer_reference_number' => 'TRF-20260919-8888',
            'proof_file' => 'vendor-payments/proofs/transfer-receipt-001.pdf',
            'amount' => 5000000,
            'status' => 'draft',
            'cabang_id' => $context['cabang']->id,
        ]);

        expect($payment->proof_file)->toBe('vendor-payments/proofs/transfer-receipt-001.pdf')
            ->and($payment->target_bank_account)->toBe('BCA 1234567890 a.n PT Supplier Utama')
            ->and($payment->transfer_reference_number)->toBe('TRF-20260919-8888');
    });

    it('requires target_bank_account and transfer_reference_number for Bank Transfer in Create form', function () {
        $context = createBaseControlContext();
        $user = User::factory()->create(['cabang_id' => $context['cabang']->id]);
        createRoleAndAssign($user, 'Super Admin', [
            'view any vendor payment',
            'view vendor payment',
            'create vendor payment',
            'view any supplier',
            'view any invoice',
        ]);

        Livewire::actingAs($user)
            ->test(CreateVendorPayment::class)
            ->fillForm([
                'payment_method' => 'Bank Transfer',
                'target_bank_account' => null,
                'transfer_reference_number' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'target_bank_account' => 'required',
                'transfer_reference_number' => 'required',
            ]);
    });
});
