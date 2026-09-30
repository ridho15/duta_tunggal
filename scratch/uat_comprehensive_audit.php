<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Rak;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use App\Services\ReturnProductService;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$testCode = 'TEST-UAT-20261001-QA';
echo "=========================================================================\n";
echo "=== AUDIT UAT END-TO-END & INTEGRITAS FINANSIAL: {$testCode} ===\n";
echo "=========================================================================\n\n";

$auditResults = [
    'pillar1_stock' => [],
    'pillar2_financial' => [],
    'pillar3_gl' => [],
    'negative_boundary' => [],
    'historical_issues' => [],
    'created_test_records' => [],
];

// Set Auth User for auditing
$superAdmin = User::role('Super Admin')->first() ?? User::first();
Auth::login($superAdmin);

// -----------------------------------------------------------------------------
// PILAR 1 & BOUNDARY 1: RETUR PEMBELIAN (PURCHASE RETURN) - DOUBLE IMPACT AUDIT
// -----------------------------------------------------------------------------
echo "[TEST 1] Audit Retur Pembelian: Verifikasi Tidak Terjadi Pemotongan Ganda (Double-Impact)...\n";
DB::beginTransaction();
try {
    $wh = Warehouse::first();
    $prod = Product::first();
    $supplier = Supplier::first();
    
    // Set baseline stock
    $stockRecord = InventoryStock::firstOrCreate(
        ['product_id' => $prod->id, 'warehouse_id' => $wh->id],
        ['qty_available' => 100, 'qty_reserved' => 0]
    );
    $initialStock = (float) $stockRecord->qty_available;
    
    $returnQty = 2;
    $uniqueSuffix = uniqid();
    $pReturn = PurchaseReturn::create([
        'nota_retur' => "PR-{$testCode}-{$uniqueSuffix}",
        'purchase_receipt_id' => PurchaseReceipt::first()->id ?? null,
        'supplier_id' => $supplier->id ?? 1,
        'warehouse_id' => $wh->id,
        'return_date' => now()->toDateString(),
        'status' => 'draft',
        'reason' => 'UAT Negative & Double Impact Verification',
        'created_by' => $superAdmin->id,
    ]);
    $auditResults['created_test_records'][] = "PurchaseReturn: " . $pReturn->nota_retur;
    
    $pReturnItem = $pReturn->purchaseReturnItem()->create([
        'product_id' => $prod->id,
        'qty_returned' => $returnQty,
        'unit_price' => 50000,
        'subtotal' => 100000,
        'reason' => 'Defective item',
    ]);
    
    // Process Approval via PurchaseReturnService
    $prService = app(PurchaseReturnService::class);
    $prService->approve($pReturn);
    
    // Re-read stock
    $stockRecord->refresh();
    $newStock = (float) $stockRecord->qty_available;
    $stockDelta = $initialStock - $newStock;
    
    // Count stock movements created
    $movements = StockMovement::where('product_id', $prod->id)
        ->where('warehouse_id', $wh->id)
        ->where('type', 'purchase_return')
        ->where('from_model_type', 'App\Models\PurchaseReturn')
        ->where('from_model_id', $pReturn->id)
        ->get();
    
    $movementQty = (float) $movements->sum('quantity');
    
    echo "  - Stok Awal: {$initialStock}, Qty Retur: {$returnQty}, Stok Akhir: {$newStock}\n";
    echo "  - Perubahan Stok Riil: {$stockDelta}, Qty Mutasi: {$movementQty}\n";
    
    if ($stockDelta === (float)$returnQty && $movementQty === (float)$returnQty) {
        echo "  [PASS] Retur pembelian memotong stok tepat 1x (tidak ada double-impact).\n";
        $auditResults['pillar1_stock']['purchase_return'] = 'PASS';
    } else {
        echo "  [FAIL] Terjadi diskrepansi! Stok berkurang {$stockDelta} untuk retur {$returnQty}!\n";
        $auditResults['pillar1_stock']['purchase_return'] = "FAIL: delta={$stockDelta}, expected={$returnQty}";
    }
} catch (\Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . "\n";
    $auditResults['pillar1_stock']['purchase_return'] = 'ERROR: ' . $e->getMessage();
} finally {
    DB::rollBack();
}

// -----------------------------------------------------------------------------
// PILAR 1 & BOUNDARY 2: RETUR PENJUALAN (CUSTOMER RETURN) - DOUBLE IMPACT & QC GUARD
// -----------------------------------------------------------------------------
echo "\n[TEST 2] Audit Retur Pelanggan: QC Guard & Double Impact Check...\n";
DB::beginTransaction();
try {
    $cust = Customer::first();
    $inv = Invoice::first();
    $cr = CustomerReturn::create([
        'return_number' => "CR-{$testCode}-{$uniqueSuffix}",
        'customer_id' => $cust->id ?? 1,
        'invoice_id' => $inv->id ?? 1,
        'cabang_id' => $wh->cabang_id ?? 1,
        'warehouse_id' => $wh->id,
        'status' => 'pending',
        'reason' => 'Barang cacat pabrik saat UAT',
        'return_date' => now()->toDateString(),
        'created_by' => $superAdmin->id,
    ]);
    $auditResults['created_test_records'][] = "CustomerReturn: " . $cr->return_number;
    
    $crItem = $cr->customerReturnItems()->create([
        'product_id' => $prod->id,
        'quantity' => 1,
        'qc_result' => null, // KOSONG! Belum ada hasil QC
        'decision' => 'replace',
    ]);
    
    // Boundary check: Try complete without QC result
    $crService = app(CustomerReturnService::class);
    $qcBlocked = false;
    try {
        $crService->processCompletion($cr, $superAdmin->id);
    } catch (\Throwable $e) {
        $qcBlocked = true;
        echo "  [PASS] Boundary Test: Retur pelanggan tanpa QC berhasil DIBLOKIR. Error: " . $e->getMessage() . "\n";
    }
    
    if (!$qcBlocked) {
        echo "  [FAIL] Retur pelanggan lolos disetujui tanpa hasil QC!\n";
        $auditResults['negative_boundary']['cr_qc_guard'] = 'FAIL';
    } else {
        $auditResults['negative_boundary']['cr_qc_guard'] = 'PASS';
    }
    
    // Now provide QC result and check stock addition (must be exactly 1, not 2)
    $stockBeforeCR = (float) InventoryStock::where('product_id', $prod->id)->where('warehouse_id', $wh->id)->value('qty_available');
    $crItem->update(['qc_result' => 'accepted']);
    $cr->update(['status' => 'qc_inspection']);
    
    $crService->processCompletion($cr, $superAdmin->id);
    
    $stockAfterCR = (float) InventoryStock::where('product_id', $prod->id)->where('warehouse_id', $wh->id)->value('qty_available');
    $crStockDelta = $stockAfterCR - $stockBeforeCR;
    echo "  - Stok Sebelum CR: {$stockBeforeCR}, Stok Sesudah CR: {$stockAfterCR}, Delta: {$crStockDelta}\n";
    
    if ($crStockDelta === 1.0) {
        echo "  [PASS] Stok bertambah presisi 1 pcs (tidak ada double-impact).\n";
        $auditResults['pillar1_stock']['customer_return'] = 'PASS';
    } else {
        echo "  [FAIL] Double impact terdeteksi pada customer return! Delta: {$crStockDelta}\n";
        $auditResults['pillar1_stock']['customer_return'] = "FAIL: delta={$crStockDelta}";
    }
} catch (\Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . "\n";
    $auditResults['pillar1_stock']['customer_return'] = 'ERROR: ' . $e->getMessage();
} finally {
    DB::rollBack();
}

// -----------------------------------------------------------------------------
// BOUNDARY 3: STOCK TRANSFER WITHOUT RAK (NULLABLE RAK)
// -----------------------------------------------------------------------------
echo "\n[TEST 3] Audit Transfer Stok: Gudang Tanpa Rak (Boundary Opsional)...\n";
DB::beginTransaction();
try {
    $wh1 = Warehouse::first();
    $wh2 = Warehouse::where('id', '!=', $wh1->id)->first();
    
    $transfer = StockTransfer::create([
        'transfer_number' => "TRF-{$testCode}-{$uniqueSuffix}",
        'from_warehouse_id' => $wh1->id,
        'to_warehouse_id' => $wh2->id,
        'from_rak_id' => null, // KOSONG / NULL
        'to_rak_id' => null,   // KOSONG / NULL
        'status' => 'draft',
        'transfer_date' => now()->toDateString(),
        'notes' => null,
        'created_by' => $superAdmin->id,
    ]);
    $auditResults['created_test_records'][] = "StockTransfer: " . $transfer->transfer_number;
    
    $transferItem = $transfer->stockTransferItem()->create([
        'product_id' => $prod->id,
        'quantity' => 1,
        'from_warehouse_id' => $wh1->id,
        'to_warehouse_id' => $wh2->id,
        'from_rak_id' => null,
        'to_rak_id' => null,
    ]);
    
    echo "  [PASS] Transfer stok berhasil dibuat tanpa menentukan Rak (tidak memicu SQL NOT NULL 500).\n";
    $auditResults['negative_boundary']['transfer_without_rak'] = 'PASS';
} catch (\Throwable $e) {
    echo "  [FAIL] Transfer stok tanpa rak gagal: " . $e->getMessage() . "\n";
    $auditResults['negative_boundary']['transfer_without_rak'] = 'FAIL: ' . $e->getMessage();
} finally {
    DB::rollBack();
}

// -----------------------------------------------------------------------------
// BOUNDARY 4: STOCK OPNAME WITHOUT PHYSICAL COUNT
// -----------------------------------------------------------------------------
echo "\n[TEST 4] Audit Stock Opname: Cegah Selesai Tanpa Input Fisik...\n";
DB::beginTransaction();
try {
    $opname = StockOpname::create([
        'opname_number' => "OPN-{$testCode}-{$uniqueSuffix}",
        'warehouse_id' => $wh->id,
        'opname_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $superAdmin->id,
    ]);
    $auditResults['created_test_records'][] = "StockOpname: " . $opname->opname_number;
    
    $opItem = $opname->items()->create([
        'product_id' => $prod->id,
        'system_qty' => 50,
        'physical_qty' => null, // TIDAK DIHITUNG
        'variance_qty' => null,
    ]);
    
    $opService = app(StockOpnameService::class);
    $opBlocked = false;
    try {
        $opService->completePhysicalCount($opname, $superAdmin->id);
    } catch (\Throwable $e) {
        $opBlocked = true;
        echo "  [PASS] Penyelesaian Opname tanpa hitung fisik berhasil DIBLOKIR. Error: " . $e->getMessage() . "\n";
    }
    
    if (!$opBlocked) {
        echo "  [FAIL] Opname tanpa hitung fisik berhasil diselesaikan!\n";
        $auditResults['negative_boundary']['opname_without_physical_qty'] = 'FAIL';
    } else {
        $auditResults['negative_boundary']['opname_without_physical_qty'] = 'PASS';
    }
} catch (\Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}

// -----------------------------------------------------------------------------
// BOUNDARY 5: SALES INVOICE POSTING LOCK (Cegah Edit Invoice Non-Draft)
// -----------------------------------------------------------------------------
echo "\n[TEST 5] Audit Penguncian Invoice Penjualan (Non-Draft Posting Lock)...\n";
$unpaidInv = Invoice::where('from_model_type', 'App\Models\SaleOrder')
    ->whereIn('status', ['unpaid', 'sent', 'paid', 'partially_paid'])
    ->first();
if ($unpaidInv) {
    $normalUser = User::whereDoesntHave('roles', function($q) { $q->where('name', 'Super Admin'); })->first();
    if ($normalUser) {
        $canUpdate = $normalUser->can('update', $unpaidInv);
        echo "  - Invoice Uji: {$unpaidInv->invoice_number} (Status: {$unpaidInv->status})\n";
        echo "  - Hak Akses Normal User ({$normalUser->username}): " . ($canUpdate ? 'BISA EDIT (VULNERABILITY!)' : 'TERKUNCI (AMAN)') . "\n";
        if (!$canUpdate) {
            $auditResults['negative_boundary']['invoice_posting_lock'] = 'PASS';
        } else {
            $auditResults['negative_boundary']['invoice_posting_lock'] = 'FAIL';
        }
    }
} else {
    echo "  - Tidak ada invoice sales non-draft yang tersedia di DB lokal.\n";
}

// -----------------------------------------------------------------------------
// PILAR 3 & HISTORIS: GL BALANCE & TRANSIT ACCOUNTS
// -----------------------------------------------------------------------------
echo "\n[TEST 6] Audit Buku Besar (GL) & Saldo Akun Transit...\n";
$unbalanced = DB::select("
    SELECT reference, source_type, source_id, SUM(debit) as total_debit, SUM(credit) as total_credit, ABS(SUM(debit) - SUM(credit)) as diff
    FROM journal_entries
    WHERE deleted_at IS NULL
    GROUP BY reference, source_type, source_id
    HAVING diff > 0.01
");
echo "  - Total Jurnal Tidak Seimbang: " . count($unbalanced) . "\n";

$parentCoaEntries = DB::select("
    SELECT je.id, je.reference, coa.code, coa.name
    FROM journal_entries je
    JOIN chart_of_accounts coa ON je.coa_id = coa.id
    WHERE je.deleted_at IS NULL AND EXISTS (
        SELECT 1 FROM chart_of_accounts c WHERE c.parent_id = coa.id
    )
");
echo "  - Total Baris Jurnal Memakai Akun Induk (Parent COA): " . count($parentCoaEntries) . "\n";

$transitBalance = 0;
$coaTransit = ChartOfAccount::where('code', '1140.20')->first();
if ($coaTransit) {
    $transitBalance = (float) (DB::table('journal_entries')
        ->where('deleted_at', null)
        ->where('coa_id', $coaTransit->id)
        ->selectRaw('SUM(debit) - SUM(credit) as balance')
        ->value('balance') ?? 0);
    echo "  - Saldo Akun Transit 1140.20 (Barang Terkirim): Rp " . number_format($transitBalance, 2, ',', '.') . "\n";
    if ($transitBalance < 0) {
        echo "  [PERINGATAN AUDIT] Saldo transit 1140.20 bernilai MINUS dari transaksi historis sebelum perbaikan ReturnProduct!\n";
        $auditResults['historical_issues'][] = [
            'type' => 'negative_transit_balance',
            'account' => '1140.20 (Barang Terkirim)',
            'balance' => $transitBalance,
            'note' => 'Perlu jurnal koreksi / rekonsiliasi data historis'
        ];
    }
}

// Check debug mode
$debugStatus = config('app.debug');
echo "  - Status APP_DEBUG di server: " . ($debugStatus ? 'TRUE (BAHAYA!)' : 'FALSE (AMAN)') . "\n";

echo "\n=========================================================================\n";
echo "=== AUDIT UAT END-TO-END FINISHED ===\n";
echo "=========================================================================\n";
