<?php

namespace Tests\Feature\Gudang;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeStockOpnameWorkflowTest extends TestCase
{
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected ?Rak $rak = null;
    protected ProductCategory $category;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-OPN',
            'nama' => 'Cabang Test Opname',
            'alamat' => 'Jl. Test Opname No. 1',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-OPN-TEST',
                'name' => 'Gudang Opname Test',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->rak = Rak::firstOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'code' => 'RAK-OPN-01'],
            ['name' => 'Rak Utama Opname']
        );

        $this->category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-OPN'],
            ['name' => 'Kategori Uji Opname', 'kenaikan_harga' => 10]
        );

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        // Pastikan akun COA persediaan & variance tersedia untuk jurnal
        ChartOfAccount::firstOrCreate(
            ['code' => '1140.01'],
            ['name' => 'Persediaan Barang Dagang Test', 'type' => 'Asset', 'normal_balance' => 'Debit']
        );
        ChartOfAccount::firstOrCreate(
            ['code' => '8000.05'],
            ['name' => 'Selisih Persediaan Test', 'type' => 'Expense', 'normal_balance' => 'Debit']
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any inventory stock',
            'create inventory stock',
            'update inventory stock',
            'delete inventory stock',
            'approve warehouse',
        ];
        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $this->authorizedUser = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'opn_tester',
            'first_name' => 'Opname',
            'last_name' => 'Tester',
            'email' => 'opn-tester@dutatunggal.co.id',
            'name' => 'Opname Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->authorizedUser->hasRole('Super Admin')) {
            $this->authorizedUser->assignRole($role);
        }

        $this->unauthorizedUser = User::withoutGlobalScopes()
            ->where('id', '!=', $this->authorizedUser->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->first() ?? User::create([
                'username' => 'unauth_user_opn',
                'first_name' => 'Unauth',
                'last_name' => 'Opname',
                'email' => 'unauth-opn@dutatunggal.co.id',
                'name' => 'Unauth Opname User',
                'password' => bcrypt('password'),
                'cabang_id' => $this->cabang->id,
            ]);
    }

    protected function createTestProduct(string $sku, string $name, float $costPrice = 40000): Product
    {
        return Product::firstOrCreate(
            ['sku' => $sku],
            [
                'name' => $name,
                'kode_merk' => 'MRK-OPN',
                'product_category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'status' => 1,
                'price_buy' => $costPrice,
                'cost_price' => $costPrice,
                'price_sell' => $costPrice * 1.3,
            ]
        );
    }

    public function test_user_can_access_stock_opname_index_page_and_see_kpi_cards(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.opname.index'));

        $response->assertOk();
        $response->assertViewIs('gudang.opname.index');
        $response->assertSee('Stock Opname & Penyesuaian Persediaan', false);
        $response->assertSee('Total Sesi Opname');
        $response->assertSee('Sedang Dihitung');
        $response->assertSee('Menunggu Approval');
        $response->assertSee('Disetujui & Disesuaikan', false);
    }

    public function test_user_can_create_stock_opname_draft(): void
    {
        $payload = [
            'opname_number' => 'OPN-TEST-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Audit stok uji coba',
            'action' => 'save_draft',
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.opname.store'), $payload);

        $response->assertRedirect();

        $opname = StockOpname::where('opname_number', $payload['opname_number'])->first();
        $this->assertNotNull($opname);
        $this->assertEquals('draft', $opname->status);
        $this->assertEquals($this->warehouse->id, $opname->warehouse_id);
    }

    public function test_starting_physical_count_populates_items_from_inventory(): void
    {
        $product = $this->createTestProduct('OPN-SKU-01', 'Barang Opname 1');

        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 35, 'qty_reserved' => 0]
        );

        $opname = StockOpname::create([
            'opname_number' => 'OPN-START-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->authorizedUser->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.opname.start', $opname));

        $response->assertRedirect(route('gudang.opname.show', $opname));

        $freshOpname = $opname->fresh();
        $this->assertEquals('in_progress', $freshOpname->status);
        $this->assertGreaterThanOrEqual(1, $freshOpname->items->count());

        $item = $freshOpname->items()->where('product_id', $product->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(35, (float) $item->system_qty);
        $this->assertNull($item->physical_qty);
    }

    public function test_user_can_save_physical_counts_and_variance_is_computed(): void
    {
        $product = $this->createTestProduct('OPN-SKU-02', 'Barang Opname 2', 50000);

        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 20, 'qty_reserved' => 0]
        );

        $opname = StockOpname::create([
            'opname_number' => 'OPN-SAVE-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'in_progress',
            'created_by' => $this->authorizedUser->id,
        ]);

        $item = StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 20,
            'physical_qty' => null,
            'unit_cost' => 50000,
            'average_cost' => 50000,
        ]);

        // Simpan hasil hitung fisik = 25 (surplus +5)
        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.opname.save-progress', $opname), [
                'counts' => [$item->id => 25],
                'notes' => [$item->id => 'Ditemukan 5 pcs di kardus cadangan'],
                'action' => 'save',
            ]);

        $response->assertRedirect(route('gudang.opname.show', $opname));

        $freshItem = $item->fresh();
        $this->assertEquals(25, (float) $freshItem->physical_qty);
        $this->assertEquals(5, (float) $freshItem->difference_qty);
        $this->assertEquals(250000, (float) $freshItem->difference_value);
    }

    public function test_completing_physical_count_transitions_to_completed(): void
    {
        $product = $this->createTestProduct('OPN-SKU-03', 'Barang Opname 3');

        $opname = StockOpname::create([
            'opname_number' => 'OPN-COMP-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'in_progress',
            'created_by' => $this->authorizedUser->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 15,
            'physical_qty' => 15,
            'unit_cost' => 25000,
            'average_cost' => 25000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.opname.complete', $opname));

        $response->assertRedirect(route('gudang.opname.show', $opname));
        $this->assertEquals('completed', $opname->fresh()->status);
    }

    public function test_user_can_recount_from_completed_state(): void
    {
        $opname = StockOpname::create([
            'opname_number' => 'OPN-REC-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'created_by' => $this->authorizedUser->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.opname.recount', $opname));

        $response->assertRedirect(route('gudang.opname.show', $opname));
        $this->assertEquals('in_progress', $opname->fresh()->status);
    }

    public function test_approving_stock_opname_creates_stock_movements_and_journal_entries(): void
    {
        $product = $this->createTestProduct('OPN-SKU-APPROVE-' . uniqid(), 'Barang Opname Approve', 30000);

        // Bersihkan stok terdahulu jika ada
        InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->delete();

        // Stok sistem awal = 10 di rak utama
        InventoryStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 10,
            'qty_reserved' => 0,
        ]);

        $opname = StockOpname::create([
            'opname_number' => 'OPN-APP-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'created_by' => $this->authorizedUser->id,
        ]);

        // Hasil fisik = 14 (+4 surplus)
        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 10,
            'physical_qty' => 14,
            'difference_qty' => 4,
            'unit_cost' => 30000,
            'average_cost' => 30000,
            'difference_value' => 120000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.opname.approve', $opname));

        $response->assertRedirect(route('gudang.opname.show', $opname));

        $freshOpname = $opname->fresh();
        $this->assertEquals('approved', $freshOpname->status);

        // Verifikasi mutasi stok penyesuaian (adjustment_in)
        $movement = StockMovement::where('from_model_type', StockOpname::class)
            ->where('from_model_id', $opname->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('adjustment_in', $movement->type);
        $this->assertEquals(4, (float) $movement->quantity);

        // Stok fisik di inventory sekarang 14 (10 + 4 selisih lebih)
        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->where('rak_id', $this->rak->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(14, (float) $stock->qty_available);

        // Verifikasi JournalEntry dibuat
        $journals = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $journals->count());
        $this->assertEquals($journals->sum('debit'), $journals->sum('credit'));
    }

    public function test_user_can_stream_stock_opname_pdf(): void
    {
        $product = $this->createTestProduct('OPN-SKU-PDF', 'Barang Opname PDF');

        $opname = StockOpname::create([
            'opname_number' => 'OPN-PDF-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'created_by' => $this->authorizedUser->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 10,
            'physical_qty' => 10,
            'unit_cost' => 20000,
            'average_cost' => 20000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.opname.pdf', $opname));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_user_can_export_stock_opname_excel(): void
    {
        $product = $this->createTestProduct('OPN-SKU-XLS', 'Barang Opname XLS');

        $opname = StockOpname::create([
            'opname_number' => 'OPN-XLS-' . uniqid(),
            'opname_date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'created_by' => $this->authorizedUser->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 10,
            'physical_qty' => 12,
            'difference_qty' => 2,
            'unit_cost' => 20000,
            'average_cost' => 20000,
            'difference_value' => 40000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.opname.excel', $opname));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_user_without_permission_cannot_access_stock_opname(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('gudang.opname.index'));

        $this->assertContains($response->status(), [403, 302]);
    }
}
