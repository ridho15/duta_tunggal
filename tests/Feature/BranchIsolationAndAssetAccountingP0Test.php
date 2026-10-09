<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseReceiptPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\QualityControlPolicy;
use App\Services\LedgerPostingService;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIsolationAndAssetAccountingP0Test extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangJakarta;
    protected Cabang $cabangBogor;
    protected User $userJakarta;
    protected User $userBogor;
    protected User $userSuperAdmin;
    protected Supplier $supplier;
    protected Currency $currency;
    protected ChartOfAccount $assetCoa;
    protected ChartOfAccount $apCoa;
    protected ChartOfAccount $inventoryCoa;
    protected ChartOfAccount $ppnMasukanCoa;
    protected Warehouse $warehouseJakarta;
    protected Warehouse $warehouseBogor;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\UnitOfMeasure::factory()->create();

        $this->cabangJakarta = Cabang::create([
            'kode' => 'JKT',
            'nama' => 'Cabang Jakarta',
            'alamat' => 'Jakarta',
        ]);

        $this->cabangBogor = Cabang::create([
            'kode' => 'BGR',
            'nama' => 'Cabang Bogor',
            'alamat' => 'Bogor',
        ]);

        $this->currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
            'is_default' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabangJakarta->id,
        ]);

        $this->warehouseJakarta = Warehouse::factory()->create([
            'cabang_id' => $this->cabangJakarta->id,
            'name' => 'Gudang Jakarta',
        ]);

        $this->warehouseBogor = Warehouse::factory()->create([
            'cabang_id' => $this->cabangBogor->id,
            'name' => 'Gudang Bogor',
        ]);

        $this->userJakarta = User::factory()->create([
            'name' => 'Purchasing Jakarta',
            'email' => 'purchasing.jkt@example.com',
            'cabang_id' => $this->cabangJakarta->id,
            'manage_type' => ['branch'],
        ]);

        $this->userBogor = User::factory()->create([
            'name' => 'Purchasing Bogor',
            'email' => 'purchasing.bgr@example.com',
            'cabang_id' => $this->cabangBogor->id,
            'manage_type' => ['branch'],
        ]);

        $this->userSuperAdmin = User::factory()->create([
            'name' => 'Owner / Super Admin',
            'email' => 'owner@example.com',
            'cabang_id' => $this->cabangJakarta->id,
            'manage_type' => ['all'],
        ]);

        // COAs
        $this->assetCoa = ChartOfAccount::create([
            'code' => '1500',
            'name' => 'HARGA PEROLEHAN ASET TETAP',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->inventoryCoa = ChartOfAccount::create([
            'code' => '1140.01',
            'name' => 'PERSEDIAAN BARANG DAGANGAN',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->apCoa = ChartOfAccount::create([
            'code' => '2110',
            'name' => 'HUTANG USAHA',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $this->ppnMasukanCoa = ChartOfAccount::create([
            'code' => '1170.06',
            'name' => 'PPN MASUKAN',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '2100.10',
            'name' => 'PEMBELIAN BELUM TERTAGIH',
            'type' => 'Liability',
            'is_active' => true,
        ]);
    }

    // ==========================================
    // P0-1: CabangScope & Policy Tests
    // ==========================================

    public function test_purchase_order_cabang_scope_isolates_records_by_branch(): void
    {
        $poJakarta = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangJakarta->id,
            'po_number' => 'PO-JKT-001',
            'order_date' => now(),
            'status' => 'draft',
            'total_amount' => 500000,
        ]);

        $poBogor = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangBogor->id,
            'po_number' => 'PO-BGR-001',
            'order_date' => now(),
            'status' => 'draft',
            'total_amount' => 800000,
        ]);

        // User Jakarta sees only Jakarta PO
        $this->actingAs($this->userJakarta);
        $visiblePos = PurchaseOrder::all();
        $this->assertTrue($visiblePos->contains('id', $poJakarta->id));
        $this->assertFalse($visiblePos->contains('id', $poBogor->id));

        // User Bogor sees only Bogor PO
        $this->actingAs($this->userBogor);
        $visiblePosBogor = PurchaseOrder::all();
        $this->assertFalse($visiblePosBogor->contains('id', $poJakarta->id));
        $this->assertTrue($visiblePosBogor->contains('id', $poBogor->id));

        // Super Admin (manage_type: ['all']) sees both
        $this->actingAs($this->userSuperAdmin);
        $visiblePosAll = PurchaseOrder::all();
        $this->assertTrue($visiblePosAll->contains('id', $poJakarta->id));
        $this->assertTrue($visiblePosAll->contains('id', $poBogor->id));
    }

    public function test_quality_control_cabang_scope_isolates_records_by_branch(): void
    {
        $qcJakarta = QualityControl::create([
            'cabang_id' => $this->cabangJakarta->id,
            'warehouse_id' => $this->warehouseJakarta->id,
            'qc_number' => 'QC-JKT-001',
            'status' => 0,
        ]);

        $qcBogor = QualityControl::create([
            'cabang_id' => $this->cabangBogor->id,
            'warehouse_id' => $this->warehouseBogor->id,
            'qc_number' => 'QC-BGR-001',
            'status' => 0,
        ]);

        $this->actingAs($this->userJakarta);
        $visibleQcs = QualityControl::all();
        $this->assertTrue($visibleQcs->contains('id', $qcJakarta->id));
        $this->assertFalse($visibleQcs->contains('id', $qcBogor->id));

        $this->actingAs($this->userBogor);
        $visibleQcsBogor = QualityControl::all();
        $this->assertFalse($visibleQcsBogor->contains('id', $qcJakarta->id));
        $this->assertTrue($visibleQcsBogor->contains('id', $qcBogor->id));
    }

    public function test_policies_block_cross_branch_access(): void
    {
        $poBogor = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangBogor->id,
            'po_number' => 'PO-BGR-002',
            'order_date' => now(),
            'status' => 'draft',
            'total_amount' => 500000,
        ]);

        $qcBogor = QualityControl::create([
            'cabang_id' => $this->cabangBogor->id,
            'warehouse_id' => $this->warehouseBogor->id,
            'qc_number' => 'QC-BGR-002',
            'status' => 0,
        ]);

        $receiptBogor = PurchaseReceipt::create([
            'cabang_id' => $this->cabangBogor->id,
            'receipt_number' => 'RC-BGR-002',
            'receipt_date' => now(),
            'received_by' => $this->userBogor->id,
            'currency_id' => $this->currency->id,
            'status' => 'draft',
        ]);

        $returnBogor = PurchaseReturn::create([
            'cabang_id' => $this->cabangBogor->id,
            'nota_retur' => 'RET-BGR-002',
            'return_date' => now(),
            'created_by' => $this->userBogor->id,
            'status' => 'draft',
        ]);

        $poPolicy = new PurchaseOrderPolicy();
        $qcPolicy = new QualityControlPolicy();
        $receiptPolicy = new PurchaseReceiptPolicy();
        $returnPolicy = new PurchaseReturnPolicy();

        // User Jakarta cannot view/update/delete Bogor records
        $this->assertFalse($poPolicy->view($this->userJakarta, $poBogor));
        $this->assertFalse($poPolicy->update($this->userJakarta, $poBogor));
        $this->assertFalse($poPolicy->delete($this->userJakarta, $poBogor));
        $this->assertFalse($poPolicy->response($this->userJakarta, $poBogor));

        $this->assertFalse($qcPolicy->view($this->userJakarta, $qcBogor));
        $this->assertFalse($qcPolicy->update($this->userJakarta, $qcBogor));
        $this->assertFalse($qcPolicy->delete($this->userJakarta, $qcBogor));

        $this->assertFalse($receiptPolicy->view($this->userJakarta, $receiptBogor));
        $this->assertFalse($receiptPolicy->update($this->userJakarta, $receiptBogor));

        $this->assertFalse($returnPolicy->view($this->userJakarta, $returnBogor));
        $this->assertFalse($returnPolicy->update($this->userJakarta, $returnBogor));

        // User canAccessCabang helper works as expected
        $this->assertFalse($this->userJakarta->canAccessCabang($this->cabangBogor->id));
        $this->assertTrue($this->userJakarta->canAccessCabang($this->cabangJakarta->id));
        $this->assertTrue($this->userSuperAdmin->canAccessCabang($this->cabangBogor->id));
    }

    // ==========================================
    // P0-2: Asset PO Accounting & Double AP Elimination
    // ==========================================

    public function test_asset_purchase_order_approval_creates_asset_and_single_acquisition_journal(): void
    {
        $this->actingAs($this->userSuperAdmin);

        $product = Product::factory()->create([
            'cabang_id' => $this->cabangJakarta->id,
            'name' => 'MacBook Pro M3 Max',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangJakarta->id,
            'po_number' => 'PO-AST-001',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_asset' => true,
            'total_amount' => 35000000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'currency_id' => $this->currency->id,
            'quantity' => 1,
            'unit_price' => 35000000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]);

        // Approve the PO
        $po->update([
            'status' => 'approved',
            'approved_by' => $this->userSuperAdmin->id,
        ]);

        // Assert asset was created
        $asset = Asset::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($asset);
        $this->assertEquals(35000000, (float) $asset->purchase_cost);
        $this->assertEquals('active', $asset->status);

        // Assert PO acquisition journal entries exist: 1 Debit Fixed Asset (1500), 1 Credit AP (2110)
        $journals = JournalEntry::where('source_type', PurchaseOrder::class)
            ->where('source_id', $po->id)
            ->where('journal_type', 'asset_acquisition')
            ->get();

        $this->assertCount(2, $journals);

        $debitEntry = $journals->where('debit', 35000000)->first();
        $this->assertNotNull($debitEntry);
        $this->assertEquals('1500', $debitEntry->coa->code);

        $creditEntry = $journals->where('credit', 35000000)->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals('2110', $creditEntry->coa->code);

        // Verify that AssetObserver did NOT create duplicate acquisition journals for the Asset model
        $assetModelJournals = JournalEntry::where('source_type', Asset::class)
            ->where('source_id', $asset->id)
            ->count();
        $this->assertEquals(0, $assetModelJournals, 'AssetObserver must not create duplicate acquisition journals when PO handles it');
    }

    public function test_invoice_for_asset_purchase_order_eliminates_double_ap_and_fictitious_inventory(): void
    {
        $this->actingAs($this->userSuperAdmin);

        $product = Product::factory()->create([
            'cabang_id' => $this->cabangJakarta->id,
            'name' => 'Server Dell PowerEdge',
            'inventory_coa_id' => $this->inventoryCoa->id,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangJakarta->id,
            'po_number' => 'PO-AST-002',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_asset' => true,
            'total_amount' => 50000000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'currency_id' => $this->currency->id,
            'quantity' => 1,
            'unit_price' => 50000000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]);

        // Approve PO (creates asset and acquisition journal: 50,000,000 Debit 1500, Credit 2110)
        $po->update([
            'status' => 'approved',
            'approved_by' => $this->userSuperAdmin->id,
        ]);

        // Invoice arrives with 11% PPN: Subtotal 50,000,000, PPN 5,500,000, Total 55,500,000
        $invoice = Invoice::create([
            'invoice_number' => 'INV-AST-002',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'subtotal' => 50000000,
            'tax' => 5500000,
            'ppn_rate' => 11,
            'total' => 55500000,
            'status' => 'draft',
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangJakarta->id,
            'accounts_payable_coa_id' => $this->apCoa->id,
            'inventory_coa_id' => $this->inventoryCoa->id,
            'ppn_masukan_coa_id' => $this->ppnMasukanCoa->id,
        ]);

        // Post the invoice to ledger
        $ledgerService = app(LedgerPostingService::class);
        $result = $ledgerService->postInvoice($invoice);

        $this->assertEquals('posted', $result['status']);

        // Check invoice journal entries:
        // Must NOT debit inventory (1140.01)!
        $invoiceEntries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $inventoryDebit = $invoiceEntries->where('coa_id', $this->inventoryCoa->id)->first();
        $this->assertNull($inventoryDebit, 'Invoice for Asset PO must NEVER debit trade inventory 1140.01');

        // Check PPN Masukan debit
        $ppnDebit = $invoiceEntries->where('coa_id', $this->ppnMasukanCoa->id)->first();
        $this->assertNotNull($ppnDebit);
        $this->assertEquals(5500000, (float) $ppnDebit->debit);

        // Check Accounts Payable credit from Invoice:
        // Because 50,000,000 was ALREADY credited by the PO approval, the invoice should ONLY credit incremental AP (5,500,000)!
        $invoiceApCredit = $invoiceEntries->where('coa_id', $this->apCoa->id)->first();
        $this->assertNotNull($invoiceApCredit);
        $this->assertEquals(5500000, (float) $invoiceApCredit->credit, 'Invoice must only credit remaining/incremental AP (PPN)');

        // TOTAL Accounts Payable across the entire ERP cycle (PO + Invoice):
        $totalApCredit = JournalEntry::where('coa_id', $this->apCoa->id)->sum('credit');
        $this->assertEquals(55500000, (float) $totalApCredit, 'Total AP across PO + Invoice must equal EXACTLY invoice total (55,500,000)');

        // TOTAL Debits across the entire ERP cycle:
        $fixedAssetDebit = JournalEntry::where('coa_id', $this->assetCoa->id)->sum('debit');
        $totalPpnDebit = JournalEntry::where('coa_id', $this->ppnMasukanCoa->id)->sum('debit');
        $this->assertEquals(50000000, (float) $fixedAssetDebit);
        $this->assertEquals(5500000, (float) $totalPpnDebit);
        $this->assertEquals(55500000, (float) ($fixedAssetDebit + $totalPpnDebit));
    }

    public function test_quality_control_for_asset_purchase_skips_trade_inventory_stock_movement(): void
    {
        $this->actingAs($this->userSuperAdmin);

        $warehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabangJakarta->id,
            'name' => 'Gudang Pusat Jakarta',
        ]);

        $product = Product::factory()->create([
            'cabang_id' => $this->cabangJakarta->id,
            'name' => 'Genset Industri 15kVA',
            'inventory_coa_id' => $this->inventoryCoa->id,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangJakarta->id,
            'po_number' => 'PO-AST-003',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_asset' => true,
            'total_amount' => 45000000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'currency_id' => $this->currency->id,
            'quantity' => 1,
            'unit_price' => 45000000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]);

        $qc = QualityControl::create([
            'qc_number' => 'QC-AST-003',
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'quantity_received' => 1,
            'passed_quantity' => 1,
            'rejected_quantity' => 0,
            'status' => 0,
            'cabang_id' => $this->cabangJakarta->id,
        ]);

        $qcService = app(QualityControlService::class);
        $qcService->createJournalEntriesAndInventoryForQC($qc);

        // Verify NO stock movements were created for this asset
        $stockMovementsCount = StockMovement::where('from_model_type', QualityControl::class)
            ->where('from_model_id', $qc->id)
            ->count();
        $this->assertEquals(0, $stockMovementsCount, 'Asset QC must not create trade inventory StockMovement');

        // Verify NO inventory journal entries were created by QC
        $qcJournalsCount = JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $qc->id)
            ->count();
        $this->assertEquals(0, $qcJournalsCount, 'Asset QC must not create trade inventory journal entries');
    }
}
