<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockOpnameService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MENYIAPKAN FIXTURES UAT MODUL 09 (INVENTORY, STOK BEBAS & OPNAME HP) ===\n";

// 1. Setup Cabang
$cabang = Cabang::where('status', true)->first() ?? Cabang::firstOrCreate(
    ['kode' => 'JKT-01'],
    ['name' => 'Cabang Pusat Jakarta', 'nama' => 'Cabang Pusat Jakarta', 'status' => 1]
);

// 2. Setup Warehouses
$whUtama = Warehouse::find(38);
if (!$whUtama) {
    $whUtama = Warehouse::firstOrCreate(
        ['kode' => 'GDG-UTM'],
        ['name' => 'Gudang Utama', 'cabang_id' => $cabang->id, 'status' => 1, 'is_quarantine' => 0]
    );
}
$whUtama->update([
    'name' => 'Gudang Utama',
    'status' => 1,
    'is_quarantine' => 0,
    'cabang_id' => $cabang->id,
]);

$whKenari = Warehouse::find(39);
if (!$whKenari) {
    $whKenari = Warehouse::firstOrCreate(
        ['kode' => 'GDG-KNR'],
        ['name' => 'Gudang Kenari', 'cabang_id' => $cabang->id, 'status' => 1, 'is_quarantine' => 0]
    );
}
$whKenari->update([
    'name' => 'Gudang Kenari',
    'status' => 1,
    'is_quarantine' => 0,
    'cabang_id' => $cabang->id,
]);

// Setup Raks
$rakUtama = Rak::firstOrCreate(
    ['warehouse_id' => $whUtama->id, 'code' => 'RAK-UTM-01'],
    ['name' => 'Rak Utama A1', 'description' => 'Rak Utama A1']
);

$rakKenari = Rak::firstOrCreate(
    ['warehouse_id' => $whKenari->id, 'code' => 'RAK-KNR-01'],
    ['name' => 'Rak Kenari B1', 'description' => 'Rak Kenari B1']
);

// 3. Setup Users & Roles
$roleWarehouse = Role::firstOrCreate(['name' => 'Warehouse Staff', 'guard_name' => 'web']);
$roleChecker = Role::firstOrCreate(['name' => 'Checker', 'guard_name' => 'web']);
$roleInvMgr = Role::firstOrCreate(['name' => 'Inventory Manager', 'guard_name' => 'web']);

// Grant permissions to roles
$permissions = [
    'view any inventory stock',
    'create inventory stock',
    'update inventory stock',
    'delete inventory stock',
    'view any stock transfer',
    'create stock transfer',
    'update stock transfer',
    'delete stock transfer',
    'view any sales order',
    'create sales order',
];

foreach ($permissions as $permName) {
    $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    if (!$roleWarehouse->hasPermissionTo($perm)) $roleWarehouse->givePermissionTo($perm);
    if (!$roleChecker->hasPermissionTo($perm)) $roleChecker->givePermissionTo($perm);
    if (!$roleInvMgr->hasPermissionTo($perm)) $roleInvMgr->givePermissionTo($perm);
}

$userStaff = User::firstOrCreate(
    ['email' => 'warehouse_staff@example.com'],
    [
        'name' => 'Warehouse Staff',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]
);
$userStaff->assignRole($roleWarehouse);
$userStaff->update(['cabang_id' => $cabang->id, 'manage_type' => ['all'], 'warehouse_id' => $whUtama->id]);

$userChecker = User::firstOrCreate(
    ['email' => 'checker@example.com'],
    [
        'name' => 'Checker Gudang',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]
);
$userChecker->assignRole($roleChecker);
$userChecker->update(['cabang_id' => $cabang->id, 'manage_type' => ['all'], 'warehouse_id' => $whUtama->id]);

$userInvMgr = User::firstOrCreate(
    ['email' => 'inventory_manager@example.com'],
    [
        'name' => 'Inventory Manager',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]
);
$userInvMgr->assignRole($roleInvMgr);
$userInvMgr->update(['cabang_id' => $cabang->id, 'manage_type' => ['all'], 'warehouse_id' => $whUtama->id]);

// 4. Setup Products
$prodA = Product::where('sku', 'PROD-TEST-A')->firstOrFail();
$prodB = Product::where('sku', 'PROD-TEST-B')->firstOrFail();

$prodOpname = Product::firstOrCreate(
    ['sku' => 'PROD-TEST-OPNAME'],
    [
        'name' => 'Pipa Tembaga AC 1/4 Inch',
        'product_category_id' => $prodA->product_category_id,
        'uom_id' => $prodA->uom_id,
        'kode_merk' => 'SP-001',
        'cabang_id' => $cabang->id,
        'cost_price' => 25000,
        'sell_price' => 35000,
        'harga_batas' => 25000,
        'is_active' => 1,
    ]
);

// 5. Setup Customer for SO test
$customer = Customer::first();
if (!$customer) {
    $customer = Customer::create([
        'code' => 'CUST-UAT-001',
        'name' => 'PT Pelanggan Logam Utama',
        'perusahaan' => 'PT Pelanggan Logam Utama',
        'phone' => '081299887766',
        'telephone' => '081299887766',
        'fax' => '-',
        'email' => 'pelanggan.logam@example.com',
        'address' => 'Jl. Industri Raya No. 10, Jakarta Barat',
        'cabang_id' => $cabang->id,
        'tipe' => 'REG',
        'tipe_pembayaran' => 'Bebas',
        'tempo_kredit' => 30,
        'kredit_limit' => 100000000,
        'nik_npwp' => '01.234.567.8-001.000',
    ]);
}

// 6. Reset & Configure Stock for UAT-INV-01 (Rumus Stok Bebas)
// Gudang Utama: PROD-TEST-A Fisik = 100, Terkunci DO = 30, Bebas = 70
InventoryStock::withTrashed()->where('product_id', $prodA->id)->forceDelete();
$stockA = InventoryStock::create([
    'warehouse_id' => $whUtama->id,
    'product_id' => $prodA->id,
    'rak_id' => $rakUtama->id,
    'qty_available' => 100,
    'qty_reserved' => 30,
    'qty_min' => 10,
    'qty_max' => 500,
]);

echo "Fixture UAT-INV-01: {$prodA->sku} di {$whUtama->name} -> Fisik: {$stockA->qty_available}, Terkunci: {$stockA->qty_reserved}, Bebas: " . ($stockA->qty_available - $stockA->qty_reserved) . "\n";

// 7. Reset & Configure Stock & Opname Session for UAT-INV-02 (Blind Count Opname HP)
// Hapus opname lama
$oldOpnames = StockOpname::where('opname_number', 'like', 'OPN-TEST-%')->get();
foreach ($oldOpnames as $oldOp) {
    $oldOp->items()->delete();
    $oldOp->forceDelete();
}

// Gudang Utama: PROD-TEST-OPNAME Fisik = 50, Terkunci = 0
InventoryStock::withTrashed()->where('product_id', $prodOpname->id)->forceDelete();
$stockOp = InventoryStock::create([
    'warehouse_id' => $whUtama->id,
    'product_id' => $prodOpname->id,
    'rak_id' => $rakUtama->id,
    'qty_available' => 50,
    'qty_reserved' => 0,
    'qty_min' => 5,
    'qty_max' => 200,
]);

// Buat Sesi Opname baru
$opname = StockOpname::create([
    'opname_number' => 'OPN-TEST-BLIND-001',
    'opname_date' => now()->format('Y-m-d'),
    'warehouse_id' => $whUtama->id,
    'status' => 'draft',
    'notes' => 'UAT-INV-02: Blind Count Stock Opname HP',
    'created_by' => $userInvMgr->id,
]);

// Start Physical Count via service agar snapshot terbuat
$opnameService = app(StockOpnameService::class);
$opnameService->startPhysicalCount($opname);

// Pastikan item opname hanya berisi PROD-TEST-OPNAME agar test fokus dan cepat
StockOpnameItem::where('stock_opname_id', $opname->id)
    ->where('product_id', '!=', $prodOpname->id)
    ->delete();

$opItem = StockOpnameItem::where('stock_opname_id', $opname->id)
    ->where('product_id', $prodOpname->id)
    ->first();

if (!$opItem) {
    $opItem = StockOpnameItem::create([
        'stock_opname_id' => $opname->id,
        'product_id' => $prodOpname->id,
        'rak_id' => $rakUtama->id,
        'system_qty' => 50,
        'physical_qty' => null,
        'unit_cost' => 25000,
        'average_cost' => 25000,
    ]);
}

$opname->update(['status' => 'in_progress']);

echo "Fixture UAT-INV-02: Sesi Opname #{$opname->opname_number} (ID: {$opname->id}) Siap di {$whUtama->name} untuk {$prodOpname->sku} (Stok Sistem: 50)\n";

// 8. Reset & Configure Stock for UAT-INV-03 (Pindah Stok Overflow)
// Hapus transfer uji lama
$oldTransfers = StockTransfer::where('transfer_number', 'like', 'ST-TEST-%')->get();
foreach ($oldTransfers as $oldTr) {
    $oldTr->stockTransferItem()->delete();
    $oldTr->forceDelete();
}

// Gudang Kenari: PROD-TEST-B Fisik = 10, Terkunci = 0 (Bebas = 10)
InventoryStock::withTrashed()->where('product_id', $prodB->id)->forceDelete();
$stockB = InventoryStock::create([
    'warehouse_id' => $whKenari->id,
    'product_id' => $prodB->id,
    'rak_id' => $rakKenari->id,
    'qty_available' => 10,
    'qty_reserved' => 0,
    'qty_min' => 2,
    'qty_max' => 100,
]);

echo "Fixture UAT-INV-03: {$prodB->sku} di {$whKenari->name} -> Stok Bebas: {$stockB->qty_available}. Siap uji transfer 15 pcs ke {$whUtama->name}\n";

echo "=== FIXTURES MODUL 09 SELESAI DISIAPKAN DENGAN SUKSES! ===\n";
