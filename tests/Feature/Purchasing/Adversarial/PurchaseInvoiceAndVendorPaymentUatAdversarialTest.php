<?php

use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(config('database.connections.mysql.database'))->toBe('duta_tunggal_test');

    $this->cabang = Cabang::factory()->create([
        'kode' => 'CBG-JKT',
        'nama' => 'Cabang Jakarta Pusat',
        'status' => true,
    ]);

    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    // Roles
    $this->roleAp = Role::firstOrCreate(['name' => 'Akuntan AP', 'guard_name' => 'web']);
    $this->roleKasir = Role::firstOrCreate(['name' => 'Kasir Pembayaran', 'guard_name' => 'web']);

    $this->apUser = User::factory()->create([
        'name' => 'Dewi Akuntan Hutang (AP)',
        'cabang_id' => $this->cabang->id,
    ]);
    $this->apUser->assignRole($this->roleAp);

    $this->kasirUser = User::factory()->create([
        'name' => 'Bambang Kasir Pembayaran',
        'cabang_id' => $this->cabang->id,
    ]);
    $this->kasirUser->assignRole($this->roleKasir);

    // Setup Akun COA Lengkap untuk Siklus Pembelian & Pembayaran
    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        [
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'currency_id' => $this->currency->id,
            'cabang_id' => $this->cabang->id,
        ]
    );

    $this->invCoa      = $coa('1140.10', 'Persediaan Barang Dagangan', 'Asset');
    $this->unbilledCoa = $coa('2100.10', 'Pembelian Belum Tertagih', 'Liability');
    $this->apCoa       = $coa('2110', 'Hutang Dagang', 'Liability');
    $this->bankCoa     = $coa('1112.01', 'Bank Central Asia (BCA)', 'Asset');

    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'kode' => 'WH-UTM',
        'name' => 'Gudang Utama',
    ]);

    $this->supplier1 = Supplier::factory()->create([
        'cabang_id' => $this->cabang->id,
        'code' => 'SUP-BAJA-01',
        'perusahaan' => 'PT Krakatau Steel Industri',
        'tempo_hutang' => 30,
    ]);

    $this->supplier2 = Supplier::factory()->create([
        'cabang_id' => $this->cabang->id,
        'code' => 'SUP-SEMEN-02',
        'perusahaan' => 'PT Semen Indonesia Perkasa',
        'tempo_hutang' => 14,
    ]);

    $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Material Konstruksi', 'kode' => 'MAT']);
    $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Batang', 'abbreviation' => 'btg']);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'name' => 'Baja Profil I-Beam 200',
        'cost_price' => 100000,
        'sell_price' => 150000,
        'inventory_coa_id' => $this->invCoa->id,
        'unbilled_purchase_coa_id' => $this->unbilledCoa->id,
        'is_active' => true,
    ]);
});

test('ADV-AP-01: Anti-Double Billing strictly rejects creating second invoice from already invoiced GRN', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-DBL-' . uniqid(),
        'supplier_id' => $this->supplier1->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1000000,
        'created_by' => $this->apUser->id,
    ]);

    $receipt = PurchaseReceipt::create([
        'receipt_number' => 'GRN-DBL-' . uniqid(),
        'purchase_order_id' => $po->id,
        'receipt_date' => now()->toDateString(),
        'currency_id' => $this->currency->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'completed',
        'received_by' => $this->apUser->id,
    ]);

    // Invoice pertama sudah terbit untuk receipt ini
    Invoice::create([
        'invoice_number' => 'INV-FIRST-' . uniqid(),
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $po->id,
        'purchase_order_ids' => [$po->id],
        'purchase_receipts' => [$receipt->id],
        'supplier_id' => $this->supplier1->id,
        'supplier_name' => $this->supplier1->perusahaan,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'cabang_id' => $this->cabang->id,
        'subtotal' => 1000000,
        'total' => 1000000,
        'status' => Invoice::STATUS_SENT,
    ]);

    // Percobaan membuat invoice kedua untuk GRN yang sama
    $payload = [
        'supplier_id' => $this->supplier1->id,
        'purchase_receipts' => [$receipt->id],
        'purchase_order_ids' => [$po->id],
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'action' => 'save_and_post',
    ];

    $response = $this->actingAs($this->apUser)->post(route('pembelian.invoices.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['selected_purchase_receipts']);
    expect(Invoice::where('supplier_id', $this->supplier1->id)->count())->toBe(1);
});

test('ADV-AP-02: Cross-Vendor Isolation in Invoice strictly rejects supplier mismatch', function () {
    $poSupplier1 = PurchaseOrder::create([
        'po_number' => 'PO-SUP1-' . uniqid(),
        'supplier_id' => $this->supplier1->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1000000,
        'created_by' => $this->apUser->id,
    ]);

    $receiptSupplier1 = PurchaseReceipt::create([
        'receipt_number' => 'GRN-SUP1-' . uniqid(),
        'purchase_order_id' => $poSupplier1->id,
        'receipt_date' => now()->toDateString(),
        'currency_id' => $this->currency->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'completed',
        'received_by' => $this->apUser->id,
    ]);

    // Payload mengirim supplier 2 padahal PO dan GRN milik supplier 1
    $payload = [
        'supplier_id' => $this->supplier2->id, // Mismatch!
        'purchase_receipts' => [$receiptSupplier1->id],
        'purchase_order_ids' => [$poSupplier1->id],
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'action' => 'save_and_post',
    ];

    $response = $this->actingAs($this->apUser)->post(route('pembelian.invoices.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['selected_supplier']);
    expect(Invoice::count())->toBe(0);
});

test('ADV-PAY-01: Anti-Overpayment rejects vendor payment allocation exceeding remaining debt', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-AP-' . uniqid(),
        'supplier_id' => $this->supplier1->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1000000,
        'created_by' => $this->apUser->id,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-OVERPAY-' . uniqid(),
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $po->id,
        'supplier_id' => $this->supplier1->id,
        'supplier_name' => $this->supplier1->perusahaan,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'cabang_id' => $this->cabang->id,
        'subtotal' => 1000000,
        'total' => 1000000,
        'status' => Invoice::STATUS_SENT,
    ]);

    $ap = AccountPayable::create([
        'invoice_id' => $invoice->id,
        'supplier_id' => $this->supplier1->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1.0,
        'total_original' => 1000000,
        'paid_original' => 0,
        'remaining_original' => 1000000,
        'total' => 1000000,
        'paid' => 0,
        'remaining' => 1000000, // Sisa hutang = Rp 1.000.000
        'status' => 'Belum Lunas',
        'cabang_id' => $this->cabang->id,
    ]);

    // Kasir mencoba membayar Rp 1.500.000 (> sisa hutang Rp 1.000.000)
    $payload = [
        'supplier_id' => $this->supplier1->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Bank Transfer',
        'coa_id' => $this->bankCoa->id,
        'selected_invoices' => [$invoice->id],
        'allocations' => [
            $invoice->id => 1500000, // Overpayment!
        ],
        'action' => 'save_and_post',
    ];

    $response = $this->actingAs($this->kasirUser)->post(route('pembelian.payments.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['allocations']);
    expect(VendorPayment::count())->toBe(0);
    expect((float) $ap->fresh()->remaining)->toBe(1000000.0);
});

test('ADV-PAY-02: Payment allocation with zero or negative amount is strictly blocked', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-ZERO-PAY-' . uniqid(),
        'supplier_id' => $this->supplier1->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1000000,
        'created_by' => $this->apUser->id,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-ZERO-' . uniqid(),
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $po->id,
        'supplier_id' => $this->supplier1->id,
        'supplier_name' => $this->supplier1->perusahaan,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'cabang_id' => $this->cabang->id,
        'subtotal' => 1000000,
        'total' => 1000000,
        'status' => Invoice::STATUS_SENT,
    ]);

    // Kasir memasukkan alokasi 0
    $payload = [
        'supplier_id' => $this->supplier1->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'coa_id' => $this->bankCoa->id,
        'selected_invoices' => [$invoice->id],
        'allocations' => [
            $invoice->id => 0, // 0 Alokasi
        ],
        'action' => 'save_and_post',
    ];

    $response = $this->actingAs($this->kasirUser)->post(route('pembelian.payments.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['allocations.' . $invoice->id]);
    expect(VendorPayment::count())->toBe(0);
});

test('UAT-AP-PAY-01: Full Three-Way Matching (PO-GRN-Invoice), AP Ledger Posting, and Full Payment Reconciliation', function () {
    // -------------------------------------------------------------------------
    // LANGKAH 1: PO & Goods Receipt (GRN) Disetujui (15 Batang @ Rp 100.000)
    // -------------------------------------------------------------------------
    $po = PurchaseOrder::create([
        'po_number' => 'PO-3WAY-' . uniqid(),
        'supplier_id' => $this->supplier1->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1500000,
        'created_by' => $this->apUser->id,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 15,
        'unit_price' => 100000,
        'subtotal' => 1500000,
    ]);

    $receipt = PurchaseReceipt::create([
        'receipt_number' => 'GRN-3WAY-' . uniqid(),
        'purchase_order_id' => $po->id,
        'receipt_date' => now()->toDateString(),
        'currency_id' => $this->currency->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'completed',
        'received_by' => $this->apUser->id,
    ]);

    $receiptItem = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'qty_received' => 15,
        'qty_accepted' => 15,
        'qty_rejected' => 0,
        'status' => 'completed',
    ]);

    // Posting GRN awal ke persediaan & Unbilled AP
    app(\App\Services\PurchaseReceiptService::class)->postPurchaseReceipt($receipt);

    // -------------------------------------------------------------------------
    // LANGKAH 2: Akuntan AP Menerbitkan Faktur Pembelian (Purchase Invoice)
    // -------------------------------------------------------------------------
    $invPayload = [
        'supplier_id' => $this->supplier1->id,
        'purchase_receipts' => [$receipt->id],
        'purchase_order_ids' => [$po->id],
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'supplier_invoice_number' => 'INV-SUP-' . uniqid(),
        'cabang_id' => $this->cabang->id,
        'action' => 'save_and_post',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 15,
                'price' => 100000,
                'po_price' => 100000,
                'total' => 1500000,
            ],
        ],
    ];

    $invResponse = $this->actingAs($this->apUser)->post(route('pembelian.invoices.store'), $invPayload);
    $invResponse->assertRedirect();
    $invResponse->assertSessionHas('success');

    $invoice = Invoice::where('from_model_type', PurchaseOrder::class)
        ->where('supplier_id', $this->supplier1->id)
        ->latest('id')
        ->first();

    // Verifikasi Dokumen Faktur
    expect($invoice)->not->toBeNull();
    expect((float) $invoice->total)->toBe(1500000.0);
    expect($invoice->status)->toBe(Invoice::STATUS_SENT);

    // Verifikasi AP Sub-Ledger
    $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
    expect($ap)->not->toBeNull();
    expect((float) $ap->total)->toBe(1500000.0);
    expect((float) $ap->remaining)->toBe(1500000.0);
    expect((float) $ap->paid)->toBe(0.0);

    // Verifikasi Jurnal 3-Way Clearing: Unbilled AP (2100.10) didebet dan Hutang Dagang (2110) dikredit
    $invJournals = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();

    expect($invJournals->count())->toBeGreaterThan(0);
    $invDebit = (float) $invJournals->sum('debit');
    $invCredit = (float) $invJournals->sum('credit');
    expect($invDebit)->toBe($invCredit);
    expect($invDebit)->toBe(1500000.0);

    // -------------------------------------------------------------------------
    // LANGKAH 3: Kasir Pembayaran Melunasi Hutang Vendor via Bank Transfer
    // -------------------------------------------------------------------------
    $payPayload = [
        'supplier_id' => $this->supplier1->id,
        'cabang_id' => $this->cabang->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Bank Transfer',
        'coa_id' => $this->bankCoa->id,
        'transfer_reference_number' => 'TRF-BCA-987654',
        'notes' => 'Pelunasan faktur bahan baku I-Beam 200',
        'selected_invoices' => [$invoice->id],
        'allocations' => [
            $invoice->id => 1500000, // Pelunasan Penuh (100%)
        ],
        'action' => 'save_and_post',
    ];

    $payResponse = $this->actingAs($this->kasirUser)->post(route('pembelian.payments.store'), $payPayload);
    $payResponse->assertRedirect();
    $payResponse->assertSessionHas('success');

    $payment = VendorPayment::latest('id')->first();

    // -------------------------------------------------------------------------
    // PEMBUKTIAN INTEGRITAS DATA 4-LAPIS (4-Layer Proof)
    // -------------------------------------------------------------------------

    // LAYER 1: Dokumen Status Lunas Sempurna
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe(VendorPayment::STATUS_PAID);
    expect((float) $payment->total_payment)->toBe(1500000.0);

    // Status Invoice otomatis berubah ke PAID
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);

    // LAYER 2: Kartu Stok Fisik Tidak Berubah saat Pembayaran
    // Stok fisik tetap 15 unit (pembayaran murni finansial, tidak menggeser stok fisik)
    expect((float) $this->product->inventoryStock()->sum('qty_available'))->toBe(15.0);

    // LAYER 3: Jurnal Akuntansi Buku Besar Pembayaran (Debit Hutang, Kredit Kas/Bank)
    $payJournals = JournalEntry::where('source_type', VendorPayment::class)
        ->where('source_id', $payment->id)
        ->get();

    expect($payJournals->count())->toBe(2);
    $payDebit = (float) $payJournals->sum('debit');
    $payCredit = (float) $payJournals->sum('credit');
    expect($payDebit)->toBe($payCredit);
    expect($payDebit)->toBe(1500000.0);

    // Debit Hutang Dagang (2110)
    $apDebitEntry = $payJournals->where('coa_id', $this->apCoa->id)->first();
    expect($apDebitEntry)->not->toBeNull();
    expect((float) $apDebitEntry->debit)->toBe(1500000.0);

    // Kredit Kas & Bank (1112.01)
    $bankCreditEntry = $payJournals->where('coa_id', $this->bankCoa->id)->first();
    expect($bankCreditEntry)->not->toBeNull();
    expect((float) $bankCreditEntry->credit)->toBe(1500000.0);

    // LAYER 4: Kartu Hutang Dagang (AP Sub-Ledger) Nol Bersih (Lunas)
    $ap->refresh();
    expect((float) $ap->paid)->toBe(1500000.0);
    expect((float) $ap->remaining)->toBe(0.0);
    expect($ap->status)->toBe('Lunas');
});
