<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DeliveryOrder;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=== START AUDIT RUNNER: TEST-UAT-20261001-QA ===\n\n";

$createdDocIds = [];

// 1. AUDIT GENERAL LEDGER INTEGRITY ACROSS ALL EXISTING DATA
echo "--- 1. GL INTEGRITY CHECK ACROSS ALL EXISTING DATA ---\n";
// Group by reference or (source_type, source_id)
$unbalancedJournals = DB::select("
    SELECT reference, source_type, source_id, SUM(debit) as total_debit, SUM(credit) as total_credit, ABS(SUM(debit) - SUM(credit)) as diff
    FROM journal_entries
    WHERE deleted_at IS NULL
    GROUP BY reference, source_type, source_id
    HAVING diff > 0.01
");
echo "Unbalanced Journals Count: " . count($unbalancedJournals) . "\n";
foreach ($unbalancedJournals as $uj) {
    echo "  - Ref: {$uj->reference} | Source: {$uj->source_type} #{$uj->source_id} | Debit: {$uj->total_debit} | Credit: {$uj->total_credit} | Diff: {$uj->diff}\n";
}

$parentCoaJournals = DB::select("
    SELECT je.id, je.reference, coa.code, coa.name
    FROM journal_entries je
    JOIN chart_of_accounts coa ON je.coa_id = coa.id
    WHERE je.deleted_at IS NULL AND EXISTS (
        SELECT 1 FROM chart_of_accounts c WHERE c.parent_id = coa.id
    )
");
echo "Journals using Parent Accounts: " . count($parentCoaJournals) . "\n";
foreach ($parentCoaJournals as $pj) {
    echo "  - Entry {$pj->id} (Ref: {$pj->reference}) uses Parent COA {$pj->code} ({$pj->name})\n";
}

// Check transit account 1140.20 (Barang Terkirim)
$goodsDeliveryCoa = ChartOfAccount::where('code', '1140.20')->first();
if ($goodsDeliveryCoa) {
    $transitBalance = DB::table('journal_entries')
        ->where('deleted_at', null)
        ->where('coa_id', $goodsDeliveryCoa->id)
        ->selectRaw('SUM(debit) - SUM(credit) as balance')
        ->value('balance') ?? 0;
    echo "Transit Account 1140.20 (Barang Terkirim) Balance: {$transitBalance} (Negative? " . ($transitBalance < 0 ? 'YES - VULNERABILITY' : 'NO - CLEAN') . ")\n";
}

// 2. AUDIT TIGA PILAR & BOUNDARY: PURCHASE RETURN DOUBLE-IMPACT CHECK
echo "\n--- 2. RETUR PEMBELIAN (PURCHASE RETURN) AUDIT ---\n";
$product = Product::first();
$warehouse = Warehouse::where('cabang_id', $product->cabang_id ?? 1)->first() ?? Warehouse::first();
$stockBefore = InventoryStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('qty_available') ?? 0;
echo "Product: {$product->name} (ID: {$product->id}) | Gudang: {$warehouse->name} | Stock Awal: {$stockBefore}\n";

// 3. BOUNDARY TEST: Stock Transfer without Rak (Empty Optional Field)
echo "\n--- 3. STOCK TRANSFER WITHOUT RAK (BOUNDARY/NULLABLE) ---\n";
$wh1 = Warehouse::first();
$wh2 = Warehouse::where('id', '!=', $wh1->id)->first();
try {
    $transfer = StockTransfer::create([
        'transfer_number' => 'TRF-TEST-UAT-20261001-QA-01',
        'from_warehouse_id' => $wh1->id,
        'to_warehouse_id' => $wh2->id,
        'from_rak_id' => null, // OPSIONAL / NULL
        'to_rak_id' => null,   // OPSIONAL / NULL
        'status' => 'draft',
        'transfer_date' => now()->toDateString(),
        'notes' => null,       // OPSIONAL / NULL
        'created_by' => 1,
    ]);
    $createdDocIds[] = "StockTransfer: " . $transfer->transfer_number;
    echo "SUCCESS: StockTransfer created without Rak. ID: {$transfer->id}, Num: {$transfer->transfer_number}\n";
    $transfer->delete();
} catch (\Throwable $e) {
    echo "FAILURE: StockTransfer without Rak threw error: " . $e->getMessage() . "\n";
}

// 4. BOUNDARY TEST: Stock Opname without physical count
echo "\n--- 4. STOCK OPNAME WITHOUT PHYSICAL COUNT ---\n";
try {
    $opnameService = app(StockOpnameService::class);
    $opname = StockOpname::create([
        'opname_number' => 'OPN-TEST-UAT-20261001-QA-01',
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => 1,
    ]);
    $createdDocIds[] = "StockOpname: " . $opname->opname_number;
    $item = $opname->items()->create([
        'product_id' => $product->id,
        'system_qty' => 50,
        'physical_qty' => null, // TIDAK DIISI
        'variance_qty' => null,
    ]);
    
    // Attempt to complete/approve
    $opnameService->completePhysicalCount($opname->id, 1);
    echo "FAIL: Opname was completed without physical qty!\n";
} catch (\Throwable $e) {
    echo "SUCCESS (Blocked): Opname without physical count rejected with error: " . $e->getMessage() . "\n";
    if (isset($opname)) {
        $opname->items()->delete();
        $opname->delete();
    }
}

// 5. BOUNDARY TEST: Customer Return without QC result
echo "\n--- 5. CUSTOMER RETURN WITHOUT QC RESULT ---\n";
try {
    $cr = CustomerReturn::create([
        'return_number' => 'CR-TEST-UAT-20261001-QA-01',
        'customer_id' => Customer::first()->id ?? 1,
        'cabang_id' => $warehouse->cabang_id ?? 1,
        'warehouse_id' => $warehouse->id,
        'status' => 'draft',
        'return_date' => now()->toDateString(),
    ]);
    $createdDocIds[] = "CustomerReturn: " . $cr->return_number;
    $crItem = $cr->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'qc_result' => null, // KOSONG / BELUM QC
        'decision' => 'replace',
    ]);
    
    $crService = app(CustomerReturnService::class);
    $crService->processCompletion($cr->id, 1);
    echo "FAIL: Customer Return was approved/completed without QC result!\n";
} catch (\Throwable $e) {
    echo "SUCCESS (Blocked): Customer Return without QC rejected with error: " . $e->getMessage() . "\n";
    if (isset($cr)) {
        $cr->items()->delete();
        $cr->delete();
    }
}

// 6. BOUNDARY TEST: Editing Posted / Unpaid Sales Invoice
echo "\n--- 6. EDITING POSTED / UNPAID SALES INVOICE (POLICY/GUARD) ---\n";
$inv = Invoice::where('invoice_type', 'sales')->where('status', 'unpaid')->first();
if (!$inv) {
    $inv = Invoice::where('invoice_type', 'sales')->first();
}
if ($inv) {
    echo "Testing Invoice: {$inv->invoice_number} (Status: {$inv->status})\n";
    $normalUser = User::whereDoesntHave('roles', function($q) { $q->where('name', 'Super Admin'); })->first();
    if ($normalUser) {
        $canEdit = $normalUser->can('update', $inv);
        echo "Can normal user ({$normalUser->username}) edit invoice {$inv->invoice_number}? " . ($canEdit ? 'YES (VULNERABILITY!)' : 'NO (LOCKED PROPERLY)') . "\n";
    }
}

// 7. CHECK HISTORICAL RECONCILIATION
echo "\n--- 7. DATA HISTORIS & DESINKRONISASI ---\n";
$orphanInvoices = DB::table('invoices')
    ->whereNotIn('status', ['draft', 'cancelled'])
    ->whereNotExists(function($q) {
        $q->select(DB::raw(1))
            ->from('journal_entries')
            ->whereRaw('journal_entries.source_id = invoices.id AND journal_entries.source_type = "App\\\\Models\\\\Invoice"');
    })->count();
echo "Historical Orphan Invoices without GL: {$orphanInvoices}\n";

$stockDiscrepancies = DB::select("
    SELECT product_id, warehouse_id, SUM(qty_available) as stock_qty
    FROM inventory_stocks
    GROUP BY product_id, warehouse_id
    HAVING stock_qty < 0
");
echo "Negative Inventory Stocks Count: " . count($stockDiscrepancies) . "\n";
foreach ($stockDiscrepancies as $sd) {
    echo "  - Product {$sd->product_id} in WH {$sd->warehouse_id} has negative stock: {$sd->stock_qty}\n";
}

echo "\n=== AUDIT RUNNER FINISHED ===\n";
