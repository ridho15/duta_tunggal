<?php

use App\Models\CashBank;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\CashBankService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Use existing user with the provided email
    $this->user = User::where('email', 'ralamzah@gmail.com')->first();
    if (!$this->user) {
        $this->user = User::factory()->create([
            'email' => 'ralamzah@gmail.com',
            'password' => bcrypt('ridho123'),
            'name' => 'Test User',
        ]);
    }

    // Seed Cabang
    $this->seed(\Database\Seeders\CabangSeeder::class);

    // Use existing data or create if not exists
    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::first();
    if (!$this->currency) {
        $this->currency = Currency::factory()->create([
            'name' => 'Indonesian Rupiah',
            'code' => 'IDR',
            'symbol' => 'Rp',
            'to_rupiah' => 1.0,
        ]);
    }
    $this->supplier = Supplier::first();
    if (!$this->supplier) {
        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Supplier',
            'code' => 'SUP-TEST',
            'email' => 'supplier@test.com',
        ]);
    }
    $this->product = Product::first();
    if (!$this->product) {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'PROD-TEST',
        ]);
    }
    
    // Use existing COAs
    $this->inventoryCoa = ChartOfAccount::where('code', 'like', '1140%')->first();
    $this->apCoa = ChartOfAccount::where('code', 'like', '2110%')->first();
    $this->cashCoa = ChartOfAccount::where('code', 'like', '1110%')->first();
    
    // Ensure product has COA mappings
    if ($this->product) {
        $this->product->update([
            'inventory_coa_id' => $this->inventoryCoa?->id,
            'unbilled_purchase_coa_id' => $this->apCoa?->id,
        ]);
    }
});

test('complete procurement to accounting flow', function () {
    // 1. Create Purchase Order
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-TEST-001',
        'order_date' => now(),
        'status' => 'draft',
        'total_amount' => 5000000, // 100 units * 50000
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 50000,
    ]);

    // Approve PO
    $po->update(['status' => 'approved']);

    expect($po->status)->toBe('approved');
    expect($po->purchaseOrderItem)->toHaveCount(1);

    // 2. Create Quality Control
    $qcService = app(QualityControlService::class);
    $qc = $qcService->createFromPurchaseOrder($po->id, [
        'inspected_by' => $this->user->name,
        'inspection_date' => now(),
        'notes' => 'Test QC inspection',
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'inspected_quantity' => 100,
                'passed_quantity' => 95,
                'rejected_quantity' => 5,
                'rejection_reason' => 'Minor defects',
            ]
        ]
    ]);

    expect($qc)->toBeInstanceOf(QualityControl::class);
    expect($qc->status)->toBe('processed');

    // 3. Create Purchase Receipt manually (QC to receipt functionality removed)
    $receiptService = app(PurchaseReceiptService::class);
    $receipt = $receiptService->createReceipt($purchaseOrder, [
        'receipt_date' => now(),
        'received_by' => $this->user->name,
        'notes' => 'Manual receipt creation',
        'items' => [
            [
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'qty_received' => 10,
                'qty_accepted' => 10,
                'qty_rejected' => 0,
            ]
        ]
    ]);

    expect($receipt)->toBeInstanceOf(PurchaseReceipt::class);
    expect($receipt->purchaseReceiptItem)->toHaveCount(1);

    $receiptItem = $receipt->purchaseReceiptItem->first();
    expect($receiptItem->qty_received)->toBe(95); // Only passed quantity
    expect($receiptItem->qty_accepted)->toBe(95);
    expect($receiptItem->qty_rejected)->toBe(5);

    // 4. Post Receipt (create journal entries)
    DB::beginTransaction();
    $receiptService->postReceipt($receipt);
    DB::commit();

    // Verify journal entries created
    $journals = JournalEntry::where('source_type', PurchaseReceipt::class)
        ->where('source_id', $receipt->id)
        ->get();

    expect($journals)->toHaveCount(2); // Debit inventory, Credit AP

    // Check inventory increase (Dr)
    $inventoryDebit = $journals->where('debit', '>', 0)
        ->where('coa_id', $this->inventoryCoa->id)
        ->first();
    expect($inventoryDebit)->not->toBeNull();
    expect($inventoryDebit->debit)->toBe(4750000.0); // 95 * 50000

    // Check AP increase (Cr)
    $apCredit = $journals->where('credit', '>', 0)
        ->where('coa_id', $this->apCoa->id)
        ->first();
    expect($apCredit)->not->toBeNull();
    expect($apCredit->credit)->toBe(4750000.0);

    // Verify stock increased
    $this->product->refresh();
    expect($this->product->stock)->toBe(95);

    // 5. Create Purchase Invoice
    $invoiceService = app(PurchaseInvoiceService::class);
    $invoice = $invoiceService->createFromReceipt($receipt->id, [
        'invoice_number' => 'INV-TEST-001',
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'ppn_percentage' => 11,
        'notes' => 'Test invoice',
    ]);

    expect($invoice)->toBeInstanceOf(PurchaseInvoice::class);
    expect($invoice->total_amount)->toBeGreaterThan(4750000); // Include PPN

    // 6. Make Vendor Payment
    $paymentService = app(CashBankService::class);
    $payment = $paymentService->createPayment([
        'supplier_id' => $this->supplier->id,
        'cash_bank_id' => $this->cashBank->id,
        'payment_date' => now(),
        'amount' => $invoice->total_amount,
        'payment_method' => 'cash',
        'reference_number' => 'PAY-TEST-001',
        'description' => 'Payment for invoice ' . $invoice->invoice_number,
        'invoices' => [
            [
                'purchase_invoice_id' => $invoice->id,
                'amount' => $invoice->total_amount,
            ]
        ]
    ]);

    expect($payment)->toBeInstanceOf(VendorPayment::class);
    expect($payment->status)->toBe('posted');

    // Verify payment journal entries
    $paymentJournals = JournalEntry::where('source_type', VendorPayment::class)
        ->where('source_id', $payment->id)
        ->get();

    expect($paymentJournals)->toHaveCount(2); // Dr AP, Cr Cash

    // Check AP decrease (Dr)
    $apDebit = $paymentJournals->where('debit', '>', 0)
        ->where('coa_id', $this->apCoa->id)
        ->first();
    expect($apDebit)->not->toBeNull();

    // Check cash decrease (Cr)
    $cashCredit = $paymentJournals->where('credit', '>', 0)
        ->where('coa_id', $this->cashCoa->id)
        ->first();
    expect($cashCredit)->not->toBeNull();

    // Verify cash bank balance decreased
    $this->cashBank->refresh();
    expect($this->cashBank->balance)->toBeLessThan(1000000);

    // 7. Verify complete flow
    expect($po->status)->toBe('approved');
    expect($qc->status)->toBe('processed');
    expect($receipt->status)->toBe('completed');
    expect($invoice->status)->toBe('posted');
    expect($payment->status)->toBe('posted');
});

test('procurement flow handles partial receipts correctly', function () {
    // Create PO with 200 units
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-PARTIAL-001',
        'status' => 'approved',
        'total_amount' => 10000000,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 200,
        'unit_price' => 50000,
    ]);

    // First QC - receive 100 units
    $qcService = app(QualityControlService::class);
    $qc1 = $qcService->createFromPurchaseOrder($po->id, [
        'inspected_by' => $this->user->name,
        'inspection_date' => now(),
        'notes' => 'First partial QC',
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'inspected_quantity' => 100,
                'passed_quantity' => 98,
                'rejected_quantity' => 2,
            ]
        ]
    ]);

    $receiptService = app(PurchaseReceiptService::class);
    $receipt1 = $receiptService->createReceiptFromQualityControl($qc1->id);

    DB::beginTransaction();
    $receiptService->postReceipt($receipt1);
    DB::commit();

    // Second QC - receive remaining 100 units
    $qc2 = $qcService->createFromPurchaseOrder($po->id, [
        'inspected_by' => $this->user->name,
        'inspection_date' => now(),
        'notes' => 'Second partial QC',
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'inspected_quantity' => 100,
                'passed_quantity' => 95,
                'rejected_quantity' => 5,
            ]
        ]
    ]);

    $receipt2 = $receiptService->createReceiptFromQualityControl($qc2->id);

    DB::beginTransaction();
    $receiptService->postReceipt($receipt2);
    DB::commit();

    // Verify total stock
    $this->product->refresh();
    expect($this->product->stock)->toBe(193); // 98 + 95

    // Verify PO status is still approved (not completed since some rejected)
    $po->refresh();
    expect($po->status)->toBe('approved');
});

test('procurement flow with rejected items creates proper journal entries', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 50000,
    ]);

    // QC with some rejections
    $qcService = app(QualityControlService::class);
    $qc = $qcService->createFromPurchaseOrder($po->id, [
        'inspected_by' => $this->user->name,
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'inspected_quantity' => 100,
                'passed_quantity' => 80,
                'rejected_quantity' => 20,
            ]
        ]
    ]);

    $receiptService = app(PurchaseReceiptService::class);
    $receipt = $receiptService->createReceiptFromQualityControl($qc->id);

    DB::beginTransaction();
    $receiptService->postReceipt($receipt);
    DB::commit();

    // Verify only accepted quantity affects inventory
    $this->product->refresh();
    expect($this->product->stock)->toBe(80);

    // Verify journal entries reflect only accepted amount
    $journals = JournalEntry::where('source_type', PurchaseReceipt::class)
        ->where('source_id', $receipt->id)
        ->get();

    $totalDebit = $journals->sum('debit');
    $totalCredit = $journals->sum('credit');

    expect($totalDebit)->toBe($totalCredit);
    expect($totalDebit)->toBe(4000000.0); // 80 * 50000
});