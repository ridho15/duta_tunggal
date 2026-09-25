<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use App\Services\StockOpnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UATPriority2StockAdjustmentAndOpnameTest extends TestCase
{
    private Cabang $cabang;
    private User $user;
    private Warehouse $warehouseWithRaks;
    private Warehouse $warehouseNoRaks;
    private Rak $rak;
    private Product $product;
    private ChartOfAccount $inventoryCoa;
    private ChartOfAccount $adjustmentExpenseCoa;
    private ChartOfAccount $adjustmentIncomeCoa;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'exchange_rate' => 1.0,
                'is_default' => true,
                'status' => 1,
            ]
        );

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-TEST-UAT2'],
            [
                'nama' => 'Cabang Test UAT Prioritas 2',
                'alamat' => 'Jl. Test No. 123',
                'status' => 1,
                'lihat_stok_cabang_lain' => true,
            ]
        );

        $this->user = User::factory()->create([
            'username' => 'uat2_' . uniqid(),
            'email' => 'uat2_' . uniqid() . '@example.com',
            'kode_user' => 'U' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        Auth::login($this->user);

        // Chart of Accounts
        $this->inventoryCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.10'],
            [
                'name' => 'Persediaan Barang Dagangan',
                'type' => 'Asset',
                'status' => 1,
            ]
        );

        $this->adjustmentExpenseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '6280.05'],
            [
                'name' => 'Biaya Administrasi Umum Lainnya',
                'type' => 'Expense',
                'status' => 1,
            ]
        );

        $this->adjustmentIncomeCoa = ChartOfAccount::firstOrCreate(
            ['code' => '7000.04'],
            [
                'name' => 'Pendapatan Luar Usaha Lainnya',
                'type' => 'Revenue',
                'status' => 1,
            ]
        );

        // Warehouses
        $this->warehouseWithRaks = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-RAK-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang Dengan Rak',
            'location' => 'Area Rak Test',
            'status' => 1,
        ]);

        $this->rak = Rak::create([
            'warehouse_id' => $this->warehouseWithRaks->id,
            'code' => 'RAK-01',
            'name' => 'Rak A-01',
        ]);

        $this->warehouseNoRaks = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-NORAK-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang Utama Tanpa Rak',
            'location' => 'Area Gudang Utama',
            'status' => 1,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Produk Test UAT Prioritas 2',
            'sku' => 'SKU-UAT2-' . strtoupper(substr(uniqid(), -4)),
            'inventory_coa_id' => $this->inventoryCoa->id,
            'cost_price' => 25000,
        ]);
    }

    public function test_stock_adjustment_creates_balanced_journal_entries_on_approval_for_increase(): void
    {
        $service = app(StockAdjustmentService::class);

        $adjustment = StockAdjustment::create([
            'adjustment_number' => StockAdjustment::generateAdjustmentNumber(),
            'adjustment_date' => now(),
            'warehouse_id' => $this->warehouseWithRaks->id,
            'adjustment_type' => 'increase',
            'reason' => 'Penyesuaian lebih fisik',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'current_qty' => 10,
            'adjusted_qty' => 15,
            'difference_qty' => 5,
            'unit_cost' => 25000,
            'difference_value' => 125000,
        ]);

        $approved = $service->approveStockAdjustment($adjustment, $this->user->id);

        $this->assertEquals('approved', $approved->status);

        $journals = JournalEntry::where('source_type', StockAdjustment::class)
            ->where('source_id', $adjustment->id)
            ->get();

        $this->assertNotEmpty($journals);
        $debitTotal = (float) $journals->sum('debit');
        $creditTotal = (float) $journals->sum('credit');

        $this->assertEquals(125000.0, $debitTotal);
        $this->assertEquals(125000.0, $creditTotal);
        $this->assertEquals($debitTotal, $creditTotal, 'Jurnal penyesuaian harus seimbang');

        $debitEntry = $journals->firstWhere('debit', '>', 0);
        $creditEntry = $journals->firstWhere('credit', '>', 0);

        $this->assertEquals($this->inventoryCoa->id, $debitEntry->coa_id, 'Debit harus ke akun Persediaan');
        $this->assertEquals($this->adjustmentIncomeCoa->id, $creditEntry->coa_id, 'Credit harus ke akun Pendapatan Penyesuaian');
        $this->assertEquals('stock_adjustment', $debitEntry->journal_type);
    }

    public function test_stock_adjustment_creates_balanced_journal_entries_on_approval_for_decrease(): void
    {
        $service = app(StockAdjustmentService::class);

        // Setup stock in rak
        InventoryStock::updateOrCreate([
            'warehouse_id' => $this->warehouseWithRaks->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
        ], [
            'qty_available' => 20,
            'qty_reserved' => 0,
        ]);

        $adjustment = StockAdjustment::create([
            'adjustment_number' => StockAdjustment::generateAdjustmentNumber(),
            'adjustment_date' => now(),
            'warehouse_id' => $this->warehouseWithRaks->id,
            'adjustment_type' => 'decrease',
            'reason' => 'Kerusakan barang di gudang',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'current_qty' => 20,
            'adjusted_qty' => 16,
            'difference_qty' => -4,
            'unit_cost' => 25000,
            'difference_value' => -100000,
        ]);

        $approved = $service->approveStockAdjustment($adjustment, $this->user->id);

        $this->assertEquals('approved', $approved->status);

        $journals = JournalEntry::where('source_type', StockAdjustment::class)
            ->where('source_id', $adjustment->id)
            ->get();

        $this->assertNotEmpty($journals);
        $debitTotal = (float) $journals->sum('debit');
        $creditTotal = (float) $journals->sum('credit');

        $this->assertEquals(100000.0, $debitTotal);
        $this->assertEquals(100000.0, $creditTotal);
        $this->assertEquals($debitTotal, $creditTotal);

        $debitEntry = $journals->firstWhere('debit', '>', 0);
        $creditEntry = $journals->firstWhere('credit', '>', 0);

        $this->assertEquals($this->adjustmentExpenseCoa->id, $debitEntry->coa_id, 'Debit harus ke akun Beban Penyesuaian');
        $this->assertEquals($this->inventoryCoa->id, $creditEntry->coa_id, 'Credit harus ke akun Persediaan');
    }

    public function test_stock_adjustment_works_with_null_rak_for_warehouses_without_raks(): void
    {
        $service = app(StockAdjustmentService::class);

        // Ensure warehouse has NO raks
        $this->assertFalse(Rak::where('warehouse_id', $this->warehouseNoRaks->id)->exists());

        // Stock in warehouse without rak
        InventoryStock::updateOrCreate([
            'warehouse_id' => $this->warehouseNoRaks->id,
            'product_id' => $this->product->id,
            'rak_id' => null,
        ], [
            'qty_available' => 30,
            'qty_reserved' => 0,
        ]);

        $adjustment = StockAdjustment::create([
            'adjustment_number' => StockAdjustment::generateAdjustmentNumber(),
            'adjustment_date' => now(),
            'warehouse_id' => $this->warehouseNoRaks->id,
            'adjustment_type' => 'decrease',
            'reason' => 'Adjustment di gudang tanpa rak',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => null, // Null rak!
            'current_qty' => 30,
            'adjusted_qty' => 25,
            'difference_qty' => -5,
            'unit_cost' => 25000,
            'difference_value' => -125000,
        ]);

        $approved = $service->approveStockAdjustment($adjustment, $this->user->id);

        $this->assertEquals('approved', $approved->status);

        // Verify stock movement was created with null rak
        $movement = StockMovement::where('from_model_type', StockAdjustment::class)
            ->where('from_model_id', $adjustment->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertNull($movement->rak_id);
        $this->assertEquals(5.0, (float) $movement->quantity);

        // Verify journal was created
        $this->assertTrue(JournalEntry::where('source_type', StockAdjustment::class)->where('source_id', $adjustment->id)->exists());
    }

    public function test_stock_opname_works_with_null_rak_for_warehouses_without_raks(): void
    {
        $service = app(StockOpnameService::class);

        $this->assertFalse(Rak::where('warehouse_id', $this->warehouseNoRaks->id)->exists());

        InventoryStock::updateOrCreate([
            'warehouse_id' => $this->warehouseNoRaks->id,
            'product_id' => $this->product->id,
            'rak_id' => null,
        ], [
            'qty_available' => 50,
            'qty_reserved' => 0,
        ]);

        $opname = StockOpname::create([
            'opname_number' => StockOpname::generateOpnameNumber(),
            'opname_date' => now(),
            'warehouse_id' => $this->warehouseNoRaks->id,
            'status' => 'completed',
            'notes' => 'Opname gudang tanpa rak',
            'created_by' => $this->user->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'rak_id' => null,
            'system_qty' => 50,
            'physical_qty' => 55, // Selisih +5
            'unit_cost' => 25000,
            'average_cost' => 25000,
        ]);

        $approved = $service->approveStockOpname($opname, $this->user->id);

        $this->assertEquals('approved', $approved->status);

        // Stock movement created
        $movement = StockMovement::where('from_model_type', StockOpname::class)
            ->where('from_model_id', $opname->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('adjustment_in', $movement->type);
        $this->assertEquals(5.0, (float) $movement->quantity);
        $this->assertNull($movement->rak_id);

        // Journal created
        $this->assertTrue(JournalEntry::where('source_type', StockOpname::class)->where('source_id', $opname->id)->exists());
    }

    public function test_stock_opname_start_physical_count_populates_warehouse_products(): void
    {
        $service = app(StockOpnameService::class);

        InventoryStock::updateOrCreate([
            'warehouse_id' => $this->warehouseNoRaks->id,
            'product_id' => $this->product->id,
            'rak_id' => null,
        ], [
            'qty_available' => 77,
            'qty_reserved' => 0,
        ]);

        $opname = StockOpname::create([
            'opname_number' => StockOpname::generateOpnameNumber(),
            'opname_date' => now(),
            'warehouse_id' => $this->warehouseNoRaks->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $count = $service->startPhysicalCount($opname);

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertEquals('in_progress', $opname->fresh()->status);

        $item = StockOpnameItem::where('stock_opname_id', $opname->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals(77.0, (float) $item->system_qty);
        $this->assertEquals(77.0, (float) $item->physical_qty);
        $this->assertEquals(0.0, (float) $item->difference_qty);
        $this->assertEquals(25000.0, (float) $item->unit_cost);
    }

    public function test_stock_opname_approval_generates_stock_movements_and_updates_inventory(): void
    {
        $service = app(StockOpnameService::class);

        $stock = InventoryStock::updateOrCreate([
            'warehouse_id' => $this->warehouseWithRaks->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
        ], [
            'qty_available' => 100,
            'qty_reserved' => 0,
        ]);

        $opname = StockOpname::create([
            'opname_number' => StockOpname::generateOpnameNumber(),
            'opname_date' => now(),
            'warehouse_id' => $this->warehouseWithRaks->id,
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        // Physical count is 92 (deficit of 8)
        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 92,
            'unit_cost' => 25000,
        ]);

        $service->approveStockOpname($opname, $this->user->id);

        $freshStock = $stock->fresh();
        $this->assertEquals(92.0, (float) $freshStock->qty_available, 'Stok fisik harus berkurang dari 100 menjadi 92');
    }

    public function test_cascading_deletes_delete_stock_adjustment_journal_entries(): void
    {
        $service = app(StockAdjustmentService::class);

        $adjustment = StockAdjustment::create([
            'adjustment_number' => StockAdjustment::generateAdjustmentNumber(),
            'adjustment_date' => now(),
            'warehouse_id' => $this->warehouseWithRaks->id,
            'adjustment_type' => 'increase',
            'reason' => 'Test cascade delete',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'current_qty' => 10,
            'adjusted_qty' => 12,
            'difference_qty' => 2,
            'unit_cost' => 25000,
            'difference_value' => 50000,
        ]);

        $service->approveStockAdjustment($adjustment, $this->user->id);

        $this->assertTrue(JournalEntry::where('source_type', StockAdjustment::class)->where('source_id', $adjustment->id)->exists());

        // Soft delete adjustment
        $adjustment->delete();

        $this->assertFalse(JournalEntry::where('source_type', StockAdjustment::class)->where('source_id', $adjustment->id)->exists());
    }
}
