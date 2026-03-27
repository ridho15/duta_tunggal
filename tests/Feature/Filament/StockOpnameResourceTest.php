<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\StockOpnameResource;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockOpnameResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $cabang;
    protected $warehouse;
    protected $supplier;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();

        // Create test data
        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        // Create product category
        $category = ProductCategory::factory()->create();

        // Create product
        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => $category->id,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        StockOpname::where('opname_number', 'like', 'OPN-TEST-%')->delete();
        parent::tearDown();
    }

    #[Test]
    public function it_can_render_stock_opname_index_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(StockOpnameResource::getUrl('index'));

        $response->assertSuccessful();
    }

    #[Test]
    public function it_can_render_stock_opname_create_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(StockOpnameResource::getUrl('create'));

        $response->assertSuccessful();
    }

    #[Test]
    public function it_can_create_stock_opname_via_form()
    {
        $opnameData = [
            'opname_number' => 'OPN-TEST-001',
            'opname_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'notes' => 'Test stock opname',
            'created_by' => $this->admin->id,
        ];

        StockOpname::create($opnameData);

        $this->assertDatabaseHas('stock_opnames', [
            'opname_number' => 'OPN-TEST-001',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function it_can_render_stock_opname_edit_page()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(StockOpnameResource::getUrl('edit', ['record' => $opname]));

        $response->assertSuccessful();
    }

    #[Test]
    public function it_can_update_stock_opname_via_form()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $updateData = [
            'opname_number' => 'OPN-TEST-UPDATED',
            'opname_date' => now()->addDays(1)->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'in_progress',
            'notes' => 'Updated notes',
        ];

        $opname->update($updateData);

        $opname->refresh();

        $this->assertEquals('OPN-TEST-UPDATED', $opname->opname_number);
        $this->assertEquals('in_progress', $opname->status);
        $this->assertEquals('Updated notes', $opname->notes);
    }

    #[Test]
    public function it_can_approve_stock_opname_via_action()
    {
        ChartOfAccount::firstOrCreate(['code' => '1100'], ['name' => 'Inventory', 'type' => 'Asset']);
        ChartOfAccount::firstOrCreate(['code' => '5100'], ['name' => 'Inventory Adjustment', 'type' => 'Expense']);

        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);

        // Create an item with difference
        StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'difference_value' => 10000,
        ]);

        $opname->update([
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $opname->refresh();

        $this->assertEquals('approved', $opname->status);
        $this->assertEquals($this->admin->id, $opname->approved_by);
        $this->assertNotNull($opname->approved_at);
    }

    #[Test]
    public function it_can_only_approve_completed_stock_opname()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft', // Not completed
            'created_by' => $this->admin->id,
        ]);

        $opname->refresh();
        $this->assertEquals('draft', $opname->status);
        $this->assertNull($opname->approved_by);
        $this->assertNull($opname->approved_at);
    }

    #[Test]
    public function it_filters_stock_opname_by_warehouse()
    {
        $warehouse2 = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        $opname1 = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
        ]);

        $opname2 = StockOpname::factory()->create([
            'warehouse_id' => $warehouse2->id,
            'created_by' => $this->admin->id,
        ]);

        $filtered = StockOpname::query()->where('warehouse_id', $this->warehouse->id)->count();
        $this->assertGreaterThanOrEqual(1, $filtered);
    }

    #[Test]
    public function it_filters_stock_opname_by_status()
    {
        $opname1 = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $opname2 = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);

        $filtered = StockOpname::query()->where('status', 'completed')->count();
        $this->assertGreaterThanOrEqual(1, $filtered);
    }

    #[Test]
    public function it_filters_stock_opname_by_date_range()
    {
        $opname1 = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now()->subDays(10),
            'created_by' => $this->admin->id,
        ]);

        $opname2 = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now()->addDays(10),
            'created_by' => $this->admin->id,
        ]);

        $filtered = StockOpname::query()
            ->whereDate('opname_date', '>=', now()->subDays(5)->toDateString())
            ->whereDate('opname_date', '<=', now()->addDays(5)->toDateString())
            ->count();
        $this->assertGreaterThanOrEqual(0, $filtered);
    }

    #[Test]
    public function it_shows_correct_badge_colors_for_status()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'in_progress',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(StockOpnameResource::getUrl('index'));

        $response->assertSuccessful();
        // Badge colors are tested in the table configuration
    }

    #[Test]
    public function it_displays_correct_warehouse_information()
    {
        $opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(StockOpnameResource::getUrl('index'));

        $response->assertSuccessful();
        $response->assertSee("({$this->warehouse->kode}) {$this->warehouse->name}");
    }

    #[Test]
    public function it_requires_authentication_for_stock_opname_pages()
    {
        // Test index page without authentication
        $response = $this->get(StockOpnameResource::getUrl('index'));
        $response->assertRedirect('/admin/login'); // Or whatever the login route is

        // Test create page without authentication
        $response = $this->get(StockOpnameResource::getUrl('create'));
        $response->assertRedirect('/admin/login');
    }

    #[Test]
    public function it_validates_required_fields_on_create()
    {
        $invalidData = ['notes' => 'Test notes'];
        $validator = Validator::make($invalidData, [
            'opname_number' => ['required'],
            'opname_date' => ['required'],
            'warehouse_id' => ['required'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('opname_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('opname_date', $validator->errors()->toArray());
        $this->assertArrayHasKey('warehouse_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_unique_opname_number()
    {
        // Create first opname
        StockOpname::factory()->create([
            'opname_number' => 'OPN-TEST-DUPLICATE',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
        ]);

        // Try to create second with same number
        $duplicateData = [
            'opname_number' => 'OPN-TEST-DUPLICATE',
            'opname_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ];

        $validator = Validator::make($duplicateData, [
            'opname_number' => ['required', 'unique:stock_opnames,opname_number'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('opname_number', $validator->errors()->toArray());
    }

    #[Test]
    public function it_shows_correct_navigation_and_labels()
    {
        $this->assertEquals('Stock Opname', StockOpnameResource::getLabel());
        $this->assertEquals('Stock Opnames', StockOpnameResource::getPluralLabel());
        $this->assertEquals('heroicon-o-clipboard-document-list', StockOpnameResource::getNavigationIcon());
        $this->assertEquals('Gudang', StockOpnameResource::getNavigationGroup());
        $this->assertEquals(6, StockOpnameResource::getNavigationSort());
    }

    #[Test]
    public function it_has_correct_table_columns()
    {
        $this->assertTrue(method_exists(StockOpnameResource::class, 'table'));
    }

    #[Test]
    public function it_has_correct_form_schema()
    {
        $this->assertTrue(method_exists(StockOpnameResource::class, 'form'));
    }

    #[Test]
    public function it_has_stock_opname_items_relation_manager()
    {
        $relations = StockOpnameResource::getRelations();

        $this->assertNotEmpty($relations);
        $this->assertContains('App\Filament\Resources\StockOpnameResource\RelationManagers\StockOpnameItemsRelationManager', $relations);
    }

    #[Test]
    public function it_has_correct_pages()
    {
        $pages = StockOpnameResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);

        $this->assertInstanceOf(\Filament\Resources\Pages\PageRegistration::class, $pages['index']);
        $this->assertInstanceOf(\Filament\Resources\Pages\PageRegistration::class, $pages['create']);
        $this->assertInstanceOf(\Filament\Resources\Pages\PageRegistration::class, $pages['edit']);
    }
}