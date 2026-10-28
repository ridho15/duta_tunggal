<?php

use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Services\InvoiceService;
use App\Services\PurchaseInvoiceAccountingService;
use App\Services\QualityControlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MENYIAPKAN FIXTURES UAT MODUL 08 (PURCHASE INVOICE & VENDOR PAYMENT) ===\n";

// 1. Setup User Admin Keuangan
$adminKeuangan = User::firstOrCreate(
    ['email' => 'admin_keuangan@example.com'],
    [
        'name' => 'Admin Keuangan',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]
);

$cabang = Cabang::where('status', true)->first() ?? Cabang::firstOrCreate(
    ['kode' => 'JKT-01'],
    ['name' => 'Cabang Pusat Jakarta', 'nama' => 'Cabang Pusat Jakarta', 'status' => 1]
);

$roleFinance = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'web']);
if (!$adminKeuangan->hasRole('Finance')) {
    $adminKeuangan->assignRole($roleFinance);
}
$adminKeuangan->update([
    'cabang_id' => $cabang->id,
    'manage_type' => ['all'],
]);

// 2. Setup Supplier dengan Data Rekening Bank Lengkap (Bug C7)
$supplier = Supplier::firstOrCreate(
    ['code' => 'SUPP-TEST-UAT-001'],
    [
        'name' => 'PT Supplier Logam Sejahtera',
        'perusahaan' => 'PT Supplier Logam Sejahtera',
        'kontak_person' => 'Bpk. Hendra Gunawan',
        'telepon' => '08123456789',
        'email' => 'supplier.logam@example.com',
        'alamat' => 'Kawasan Industri Pulogadung No. 45, Jakarta Timur',
        'tempo_hutang' => 30,
        'cabang_id' => $cabang->id,
    ]
);

$supplier->update([
    'nama_bank' => 'BCA',
    'bank_name' => 'BCA',
    'nomor_rekening' => '1234567890',
    'rekening_bank' => '1234567890',
    'nama_rekening' => 'PT Abdi Karya Sejahtera',
    'atas_nama' => 'PT Abdi Karya Sejahtera',
]);

// 3. Setup Produk
$product = Product::where('sku', 'PROD-TEST-A')->firstOrFail();

// 4. Setup Akun Kas/Bank Pengeluar
$coaKasBank = ChartOfAccount::query()->cashBank()->where('is_active', true)->first();
if (!$coaKasBank) {
    $coaKasBank = ChartOfAccount::create([
        'code' => '1102.01',
        'name' => 'Bank BCA Operasional',
        'type' => 'Cash & Bank',
        'normal_balance' => 'Debit',
        'currency_code' => 'IDR',
        'is_active' => true,
        'cabang_id' => $cabang->id,
    ]);
}

echo "Admin Keuangan: {$adminKeuangan->email}\n";
echo "Supplier: {$supplier->perusahaan} (Bank: {$supplier->bank_name}, Rek: {$supplier->rekening_bank} a.n. {$supplier->atas_nama})\n";
echo "Akun Pengeluar: ({$coaKasBank->code}) {$coaKasBank->name}\n";

// 5. Bersihkan data pengujian lama modul 08
$oldPos = PurchaseOrder::whereIn('po_number', [
    'PO-TEST-AP-10PCS',
    'PO-TEST-AP-OVERFLOW',
])->get();

foreach ($oldPos as $oldPo) {
    // Bersihkan invoice terkait
    $invoices = Invoice::where('from_model_type', PurchaseOrder::class)
        ->where('from_model_id', $oldPo->id)
        ->get();
    foreach ($invoices as $inv) {
        VendorPaymentDetail::where('invoice_id', $inv->id)->delete();
        AccountPayable::where('invoice_id', $inv->id)->delete();
        $inv->invoiceItem()->delete();
        $inv->forceDelete();
    }

    foreach ($oldPo->purchaseReceipt as $rec) {
        PurchaseReturn::where('purchase_receipt_id', $rec->id)->each(function ($pr) {
            $pr->purchaseReturnItem()->forceDelete();
            $pr->forceDelete();
        });
        StockMovement::where('meta->purchase_receipt_id', $rec->id)->delete();
        $rec->purchaseReceiptItem()->forceDelete();
        $rec->update(['status' => 'draft']);
        PurchaseReceipt::withoutEvents(fn () => $rec->forceDelete());
    }
    $oldPo->purchaseOrderItem()->forceDelete();
    $oldPo->forceDelete();
}

// 6. Siapkan Transaksi UAT-AP-01:
// PO 10 pcs @ Rp 10.000 -> GRN (8 baik, 2 reject) -> Invoice terbit Rp 80.000
$po10 = PurchaseOrder::create([
    'po_number' => 'PO-TEST-AP-10PCS',
    'supplier_id' => $supplier->id,
    'cabang_id' => $cabang->id,
    'warehouse_id' => 28,
    'order_date' => now(),
    'expected_date' => now()->addDays(7),
    'status' => 'approved',
    'created_by' => $adminKeuangan->id,
    'subtotal' => 100000,
    'total' => 100000,
]);

$po10Item = PurchaseOrderItem::create([
    'purchase_order_id' => $po10->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 10000,
    'subtotal' => 100000,
    'total' => 100000,
]);

// Buat Penerimaan Barang: 8 Baik, 2 Reject
$grnController = app(\App\Http\Controllers\Gudang\PenerimaanBarangController::class);
$receipt10 = PurchaseReceipt::create([
    'receipt_number' => 'GRN-TEST-AP-10PCS',
    'purchase_order_id' => $po10->id,
    'cabang_id' => $cabang->id,
    'received_by' => $adminKeuangan->id,
    'currency_id' => 1,
    'receipt_date' => now()->toDateString(),
    'status' => 'completed',
    'notes' => 'Penerimaan fisik 10 pcs: 8 lolos QC masuk gudang, 2 reject retur vendor.',
]);

$receipt10Item = PurchaseReceiptItem::create([
    'purchase_receipt_id' => $receipt10->id,
    'purchase_order_item_id' => $po10Item->id,
    'product_id' => $product->id,
    'warehouse_id' => 28,
    'qty_received' => 10,
    'qty_accepted' => 8,
    'qty_rejected' => 2,
    'reason_rejected' => 'Cacat fisik dan retak mikro',
]);

// Jalankan auto return & QC untuk receipt 10
$refMethod = new ReflectionMethod($grnController, 'handleReceivingQcAndAutoReturn');
$refMethod->invoke($grnController, $receipt10);

// Catat stock movement masuk hanya 8 pcs
StockMovement::create([
    'product_id' => $product->id,
    'warehouse_id' => 28,
    'quantity' => 8,
    'type' => 'purchase_in',
    'from_model_type' => PurchaseReceipt::class,
    'from_model_id' => $receipt10->id,
    'meta' => ['purchase_receipt_id' => $receipt10->id],
    'date' => now(),
]);

// Terbitkan Faktur Pembelian untuk 8 pcs (Rp 80.000)
$invoiceService = app(InvoiceService::class);
$invNumber10 = $invoiceService->generatePurchaseInvoiceNumber();
$invoice10 = Invoice::create([
    'invoice_number' => $invNumber10,
    'supplier_invoice_number' => 'INV-SUPP-10PCS',
    'from_model_type' => PurchaseOrder::class,
    'from_model_id' => $po10->id,
    'purchase_order_ids' => [$po10->id],
    'purchase_receipts' => [$receipt10->id],
    'supplier_id' => $supplier->id,
    'supplier_name' => $supplier->perusahaan,
    'invoice_date' => now()->toDateString(),
    'due_date' => now()->addDays(30)->toDateString(),
    'cabang_id' => $cabang->id,
    'subtotal' => 80000,
    'dpp' => 80000,
    'tax' => 0,
    'ppn_rate' => 0,
    'tipe_pajak' => 'Include',
    'total' => 80000,
    'status' => Invoice::STATUS_SENT,
]);

InvoiceItem::create([
    'invoice_id' => $invoice10->id,
    'product_id' => $product->id,
    'quantity' => 8,
    'price' => 10000,
    'po_price' => 10000,
    'subtotal' => 80000,
    'total' => 80000,
]);

AccountPayable::create([
    'invoice_id' => $invoice10->id,
    'supplier_id' => $supplier->id,
    'total' => 80000,
    'paid' => 0,
    'remaining' => 80000,
    'status' => 'unpaid',
    'cabang_id' => $cabang->id,
]);

echo "Fixture UAT-AP-01 Siap: Invoice #{$invoice10->invoice_number} (ID: {$invoice10->id}, Sisa Hutang: Rp " . number_format($invoice10->total, 0, ',', '.') . ")\n";

// 7. Siapkan Transaksi UAT-AP-03: Faktur Pembelian Rp 500.000 untuk pengujian overpayment
$po50 = PurchaseOrder::create([
    'po_number' => 'PO-TEST-AP-OVERFLOW',
    'supplier_id' => $supplier->id,
    'cabang_id' => $cabang->id,
    'warehouse_id' => 28,
    'order_date' => now(),
    'expected_date' => now()->addDays(7),
    'status' => 'approved',
    'created_by' => $adminKeuangan->id,
    'subtotal' => 500000,
    'total' => 500000,
]);

$invNumber50 = $invoiceService->generatePurchaseInvoiceNumber();
$invoice50 = Invoice::create([
    'invoice_number' => $invNumber50,
    'supplier_invoice_number' => 'INV-SUPP-500K',
    'from_model_type' => PurchaseOrder::class,
    'from_model_id' => $po50->id,
    'purchase_order_ids' => [$po50->id],
    'supplier_id' => $supplier->id,
    'supplier_name' => $supplier->perusahaan,
    'invoice_date' => now()->toDateString(),
    'due_date' => now()->addDays(30)->toDateString(),
    'cabang_id' => $cabang->id,
    'subtotal' => 500000,
    'dpp' => 500000,
    'tax' => 0,
    'ppn_rate' => 0,
    'tipe_pajak' => 'Include',
    'total' => 500000,
    'status' => Invoice::STATUS_SENT,
]);

InvoiceItem::create([
    'invoice_id' => $invoice50->id,
    'product_id' => $product->id,
    'quantity' => 50,
    'price' => 10000,
    'po_price' => 10000,
    'subtotal' => 500000,
    'total' => 500000,
]);

AccountPayable::create([
    'invoice_id' => $invoice50->id,
    'supplier_id' => $supplier->id,
    'total' => 500000,
    'paid' => 0,
    'remaining' => 500000,
    'status' => 'unpaid',
    'cabang_id' => $cabang->id,
]);

echo "Fixture UAT-AP-03 Siap: Invoice #{$invoice50->invoice_number} (ID: {$invoice50->id}, Sisa Hutang: Rp " . number_format($invoice50->total, 0, ',', '.') . ")\n";

echo "=== FIXTURES MODUL 08 SELESAI DISIAPKAN DENGAN SUKSES! ===\n";
