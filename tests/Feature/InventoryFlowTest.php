<?php

use App\Filament\Resources\InventoryStockResource\Pages\CreateInventoryStock;
use App\Filament\Resources\InventoryStockResource\Pages\EditInventoryStock;
use App\Filament\Resources\InventoryStockResource\Pages\ViewInventoryStock;
use App\Filament\Resources\WarehouseResource\Pages\CreateWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\EditWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\ViewWarehouse;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Currency;
use App\Services\PurchaseReceiptService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChartOfAccountSeeder::class);

    $this->user = User::factory()->create();
    $this->cabang = Cabang::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->uom = UnitOfMeasure::factory()->create();
    $this->category = ProductCategory::factory()->create([
        'cabang_id' => $this->cabang->id,
    ]);
    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'supplier_id' => $this->supplier->id,
        'product_category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'view any warehouse',
        'view warehouse',
        'create warehouse',
        'update warehouse',
        'view any inventory stock',
        'view inventory stock',
        'create inventory stock',
        'update inventory stock',
        'view any cabang',
        'view cabang',
        'view any supplier',
        'view supplier',
        'view any product category',
        'view product category',
        'view any unit of measure',
        'view unit of measure',
        'view any chart of account',
        'view chart of account',
        'view any product',
        'view product',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $this->user->givePermissionTo($permissions);
    Auth::login($this->user);
});

it('creates a warehouse through the Filament create page', function () {
    $uniqueCode = 'WH-' . time() . '-' . rand(100, 999);

    Livewire::test(CreateWarehouse::class)
        ->fillForm([
            'kode' => $uniqueCode,
            'name' => 'Warehouse Test',
            'cabang_id' => $this->cabang->id,
            'location' => 'Test Location Address',
            'telepon' => '081234567890',
            'tipe' => 'Kecil',
            'status' => true,
            'warna_background' => '#ffffff',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $warehouse = Warehouse::where('kode', $uniqueCode)->first();

    expect($warehouse)
        ->not->toBeNull()
        ->and($warehouse->name)->toBe('Warehouse Test')
        ->and($warehouse->cabang_id)->toBe($this->cabang->id)
        ->and($warehouse->tipe)->toBe('Kecil');
});

it('creates racks for a warehouse', function () {
    $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

    // Create rack through direct model (since Rak might not have Filament resource)
    $rak = Rak::create([
        'name' => 'Rack A1',
        'code' => 'RACK-A1',
        'warehouse_id' => $warehouse->id,
    ]);

    expect($rak)
        ->not->toBeNull()
        ->and($rak->name)->toBe('Rack A1')
        ->and($rak->warehouse_id)->toBe($warehouse->id);

    // Verify relationship
    $warehouse->refresh();
    expect($warehouse->rak)->toHaveCount(1);
    expect($warehouse->rak->first()->name)->toBe('Rack A1');
});

it('initializes inventory stock through Filament', function () {
    $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);

    Livewire::test(CreateInventoryStock::class)
        ->fillForm([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
            'qty_min' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $inventoryStock = InventoryStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($inventoryStock)
        ->not->toBeNull()
        ->and($inventoryStock->qty_available)->toBe(100.0)
        ->and($inventoryStock->qty_reserved)->toBe(0.0)
        ->and($inventoryStock->rak_id)->toBe($rak->id);
});

it('validates stock locations and relationships', function () {
    $warehouse1 = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $warehouse2 = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

    $rak1 = Rak::factory()->create(['warehouse_id' => $warehouse1->id]);
    $rak2 = Rak::factory()->create(['warehouse_id' => $warehouse2->id]);

    // Create inventory stocks in different locations
    $stock1 = InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse1->id,
        'rak_id' => $rak1->id,
        'qty_available' => 50,
        'qty_reserved' => 5,
        'qty_min' => 10,
    ]);

    $stock2 = InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse2->id,
        'rak_id' => $rak2->id,
        'qty_available' => 75,
        'qty_reserved' => 10,
        'qty_min' => 15,
    ]);

    // Validate relationships
    expect($stock1->warehouse->id)->toBe($warehouse1->id);
    expect($stock1->rak->id)->toBe($rak1->id);
    expect($stock1->product->id)->toBe($this->product->id);

    expect($stock2->warehouse->id)->toBe($warehouse2->id);
    expect($stock2->rak->id)->toBe($rak2->id);
    expect($stock2->product->id)->toBe($this->product->id);

    // Validate warehouse relationships
    $warehouse1->refresh();
    $warehouse2->refresh();

    expect($warehouse1->rak)->toHaveCount(1);
    expect($warehouse2->rak)->toHaveCount(1);

    expect($warehouse1->inventoryStock)->toHaveCount(1);
    expect($warehouse2->inventoryStock)->toHaveCount(1);
});

it('tests multi-warehouse support', function () {
    $cabang2 = Cabang::factory()->create();

    $warehouse1 = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'name' => 'Main Warehouse'
    ]);
    $warehouse2 = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'name' => 'Secondary Warehouse'
    ]);
    $warehouse3 = Warehouse::factory()->create([
        'cabang_id' => $cabang2->id,
        'name' => 'Branch Warehouse'
    ]);

    $rak1 = Rak::factory()->create(['warehouse_id' => $warehouse1->id]);
    $rak2 = Rak::factory()->create(['warehouse_id' => $warehouse2->id]);
    $rak3 = Rak::factory()->create(['warehouse_id' => $warehouse3->id]);

    // Create inventory across multiple warehouses
    InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse1->id,
        'rak_id' => $rak1->id,
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 10,
    ]);

    InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse2->id,
        'rak_id' => $rak2->id,
        'qty_available' => 50,
        'qty_reserved' => 0,
        'qty_min' => 5,
    ]);

    InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse3->id,
        'rak_id' => $rak3->id,
        'qty_available' => 25,
        'qty_reserved' => 0,
        'qty_min' => 2,
    ]);

    // Test total inventory across warehouses
    $totalStock = InventoryStock::where('product_id', $this->product->id)->sum('qty_available');
    expect($totalStock)->toBe(175.0);

    // Test warehouse-specific queries
    $warehouse1Stock = InventoryStock::where('warehouse_id', $warehouse1->id)
        ->where('product_id', $this->product->id)
        ->sum('qty_available');
    expect($warehouse1Stock)->toBe(100.0);

    $warehouse2Stock = InventoryStock::where('warehouse_id', $warehouse2->id)
        ->where('product_id', $this->product->id)
        ->sum('qty_available');
    expect($warehouse2Stock)->toBe(50.0);

    // Test branch-specific queries
    $branch1Total = InventoryStock::whereHas('warehouse', function($q) {
        $q->where('cabang_id', $this->cabang->id);
    })->where('product_id', $this->product->id)->sum('qty_available');
    expect($branch1Total)->toBe(150.0);

    $branch2Total = InventoryStock::whereHas('warehouse', function($q) use ($cabang2) {
        $q->where('cabang_id', $cabang2->id);
    })->where('product_id', $this->product->id)->sum('qty_available');
    expect($branch2Total)->toBe(25.0);
});

it('edits inventory stock through Filament', function () {
    $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);

    $inventoryStock = InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 50,
        'qty_reserved' => 5,
        'qty_min' => 10,
    ]);

    Livewire::test(EditInventoryStock::class, ['record' => $inventoryStock->id])
        ->fillForm([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'qty_available' => 75,
            'qty_reserved' => 10,
            'qty_min' => 15,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $inventoryStock->refresh();
    expect($inventoryStock->qty_available)->toBe(75.0);
    expect($inventoryStock->qty_reserved)->toBe(10.0);
    expect($inventoryStock->qty_min)->toBe(15.0);
});

it('displays warehouse details on the Filament view page', function () {
    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'name' => 'Test Warehouse View',
        'kode' => 'WH-VIEW-' . uniqid(),
    ]);

    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);

    Livewire::test(ViewWarehouse::class, ['record' => $warehouse->id])
        ->assertOk();

    // Verify warehouse data is displayed
    expect($warehouse->name)->toBe('Test Warehouse View');
    expect($warehouse->rak)->toHaveCount(1);
    expect($warehouse->rak->first()->id)->toBe($rak->id);
});

it('displays inventory stock details on the Filament view page', function () {
    $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);

    $inventoryStock = InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 100,
        'qty_reserved' => 10,
        'qty_min' => 20,
    ]);

    Livewire::test(ViewInventoryStock::class, ['record' => $inventoryStock->id])
        ->assertOk();

    // Verify relationships are loaded correctly
    expect($inventoryStock->product->id)->toBe($this->product->id);
    expect($inventoryStock->warehouse->id)->toBe($warehouse->id);
    expect($inventoryStock->rak->id)->toBe($rak->id);
});

it('covers the inventory management flow through finance posting', function () {
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);

    $inventoryCoa = ChartOfAccount::factory()->create([
        'code' => '1140.55',
        'name' => 'Inventory - Test Flow',
        'type' => 'Asset',
    ]);

    $unbilledCoa = ChartOfAccount::factory()->create([
        'code' => '2190.55',
        'name' => 'Unbilled Purchases - Test Flow',
        'type' => 'Liability',
    ]);

    $tempProcurementCoa = ChartOfAccount::factory()->create([
        'code' => '1400.55',
        'name' => 'Temporary Procurement - Test Flow',
        'type' => 'Asset',
    ]);

    $category = ProductCategory::factory()->create(['cabang_id' => $branch->id]);
    $supplier = Supplier::factory()->create();

    $product = Product::factory()->create([
        'cabang_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'cost_price' => 50,
        'inventory_coa_id' => $inventoryCoa->id,
        'unbilled_purchase_coa_id' => $unbilledCoa->id,
        'temporary_procurement_coa_id' => $tempProcurementCoa->id,
        'is_raw_material' => false,
        'is_manufacture' => false,
        'is_active' => true,
    ]);

    $user = User::factory()->create(['cabang_id' => $branch->id]);

    $orderDate = Carbon::now();

    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-TEST-INV',
        'order_date' => $orderDate,
        'status' => 'approved',
        'expected_date' => $orderDate->copy()->addDays(7),
        'total_amount' => 500,
        'is_asset' => false,
        'warehouse_id' => $warehouse->id,
        'tempo_hutang' => 30,
        'note' => null,
        'created_by' => $user->id,
    ]);

    $purchaseOrderItem = PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 50,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'currency_id' => $currency->id,
    ]);

    $purchaseReceipt = PurchaseReceipt::create([
        'receipt_number' => 'RN-INV-FLOW',
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_date' => $orderDate->copy()->addDay(),
        'received_by' => $user->id,
        'notes' => null,
        'currency_id' => $currency->id,
        'other_cost' => 0,
        'status' => 'completed',
    ]);

    $purchaseReceiptItem = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_accepted' => 10,
        'warehouse_id' => $warehouse->id,
        'is_sent' => false,
        'rak_id' => $rak->id,
    ]);

    // Send item to QC first to create temporary procurement entries
    $service = app(PurchaseReceiptService::class);
    $qcResult = $service->createTemporaryProcurementEntriesForReceiptItem($purchaseReceiptItem);
    expect($qcResult['status'])->toBe('posted');

    $movementValue = 500;

    StockMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 10,
        'value' => $movementValue,
        'type' => 'purchase_in',
        'date' => Carbon::now(),
        'meta' => [
            'source' => 'purchase_receipt',
            'purchase_receipt_id' => $purchaseReceipt->id,
        ],
        'from_model_type' => PurchaseReceipt::class,
        'from_model_id' => $purchaseReceipt->id,
    ]);

    $inventorySnapshot = InventoryStock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($inventorySnapshot)->not()->toBeNull();
    expect((float) $inventorySnapshot->qty_available)->toBe(10.0);

    // Post purchase receipt after QC approval
    $result = $service->postPurchaseReceipt($purchaseReceipt->fresh('purchaseReceiptItem.purchaseOrderItem.product'));

    expect($result['status'])->toBe('posted');

    $entries = JournalEntry::where('source_type', PurchaseReceiptItem::class)
        ->where('source_id', $purchaseReceiptItem->id)
        ->where(function ($query) {
            $query->where('description', 'like', '%Inventory Stock%')
                  ->orWhere('description', 'like', '%Close Temporary Procurement%');
        })
        ->get();

    expect($entries)->toHaveCount(2);
    expect((float) $entries->sum('debit'))->toBe(500.0);
    expect((float) $entries->sum('credit'))->toBe(500.0);

    $inventoryEntry = $entries->firstWhere('coa_id', $inventoryCoa->id);
    $tempProcurementEntry = $entries->firstWhere('coa_id', $tempProcurementCoa->id);

    expect($inventoryEntry)->not()->toBeNull();
    expect((float) $inventoryEntry->debit)->toBe(500.0);
    expect((float) $inventoryEntry->credit)->toBe(0.0);

    expect($tempProcurementEntry)->not()->toBeNull();
    expect((float) $tempProcurementEntry->credit)->toBe(500.0);
    expect((float) $tempProcurementEntry->debit)->toBe(0.0);
});
