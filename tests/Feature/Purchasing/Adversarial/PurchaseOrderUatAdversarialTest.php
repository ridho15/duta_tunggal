<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ApprovalControlService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    // Permissions
    $poPerms = [
        'view any purchase order',
        'view purchase order',
        'create purchase order',
        'update purchase order',
        'delete purchase order',
        'response purchase order',
        'request purchase order',
    ];
    foreach ($poPerms as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    // Role definitions sesuai ApprovalControlService
    $this->roleStaffPurchasing = Role::firstOrCreate(['name' => 'Staff Purchasing', 'guard_name' => 'web']);
    $this->roleStaffPurchasing->syncPermissions($poPerms);

    $this->rolePurchasingManager = Role::firstOrCreate(['name' => 'Purchasing Manager', 'guard_name' => 'web']);
    $this->rolePurchasingManager->syncPermissions($poPerms);

    $this->roleOwner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    $this->roleOwner->syncPermissions($poPerms);

    // Users
    $this->staffPurchasing = User::factory()->create([
        'name' => 'Budi Staf Pengadaan',
        'cabang_id' => $this->cabang->id,
    ]);
    $this->staffPurchasing->assignRole($this->roleStaffPurchasing);

    $this->purchasingManager = User::factory()->create([
        'name' => 'Siti Manajer Purchasing',
        'cabang_id' => $this->cabang->id,
    ]);
    $this->purchasingManager->assignRole($this->rolePurchasingManager);

    $this->ownerUser = User::factory()->create([
        'name' => 'Pak Hendra Direktur / Owner',
        'cabang_id' => $this->cabang->id,
    ]);
    $this->ownerUser->assignRole($this->roleOwner);

    // Master Data
    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'kode' => 'WH-PST',
        'name' => 'Gudang Pusat',
    ]);

    $this->supplier = Supplier::factory()->create([
        'cabang_id' => $this->cabang->id,
        'code' => 'SUP-LOGAM-01',
        'perusahaan' => 'PT Krakatau Baja Abadi',
        'tempo_hutang' => 30,
    ]);

    $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Material Logam', 'kode' => 'MET']);
    $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Batang', 'abbreviation' => 'btg']);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'name' => 'Baja Profil H-Beam 150',
        'cost_price' => 500000,
        'sell_price' => 750000,
        'is_active' => true,
    ]);

    stkSetStock($this->product, $this->warehouse, 50.0);
});

test('ADV-PO-01: Anti-Self-Approval (Segregation of Duties) blocks staff or creator from approving own PO', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-SELF-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'total_amount' => 5000000,
        'created_by' => $this->staffPurchasing->id, // Pembuat adalah staf
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 10,
        'unit_price' => 500000,
        'subtotal' => 5000000,
    ]);

    // Pembuat mencoba menyetujui PO miliknya sendiri
    $response = $this->actingAs($this->staffPurchasing)->post(route('pembelian.po.approve', $po->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $errorMessage = session('error');
    expect($errorMessage)->toContain('Pemisahan tugas (Segregation of Duties)');
    expect($po->fresh()->status)->toBe('draft');
    expect($po->fresh()->approved_by)->toBeNull();
});

test('ADV-PO-02: Multi-level approval tier limit (> Rp 10.000.000) blocks Purchasing Manager and requires Direksi/Owner', function () {
    // PO bernilai Rp 25.000.000 (> batas Rp 10.000.000)
    $po = PurchaseOrder::create([
        'po_number' => 'PO-TIER-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'total_amount' => 25000000,
        'created_by' => $this->staffPurchasing->id,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 50,
        'unit_price' => 500000,
        'subtotal' => 25000000,
    ]);

    // Purchasing Manager (bukan pembuat) mencoba menyetujui PO di atas Rp 10.000.000
    $response = $this->actingAs($this->purchasingManager)->post(route('pembelian.po.approve', $po->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $errorMessage = session('error');
    expect($errorMessage)->toContain('Persetujuan bertingkat');
    expect($errorMessage)->toContain('Rp 10.000.000');
    expect($po->fresh()->status)->toBe('draft');
    expect($po->fresh()->approved_by)->toBeNull();
});

test('ADV-PO-03: Secondary approval on already approved or closed Purchase Order is strictly rejected', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-ALREADY-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved', // Sudah disetujui sebelumnya
        'date_approved' => now(),
        'approved_by' => $this->ownerUser->id,
        'total_amount' => 5000000,
        'created_by' => $this->staffPurchasing->id,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 10,
        'unit_price' => 500000,
        'subtotal' => 5000000,
    ]);

    // Percobaan approval ulang
    $response = $this->actingAs($this->ownerUser)->post(route('pembelian.po.approve', $po->id));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Hanya PO berstatus Draf atau Menunggu Persetujuan yang dapat disetujui.');
});

test('ADV-PO-04: Exceeding linked Order Request remaining quantity is strictly blocked during approval', function () {
    $or = OrderRequest::create([
        'request_number' => 'OR-' . uniqid(),
        'cabang_id' => $this->cabang->id,
        'request_date' => now()->toDateString(),
        'status' => 'approved',
        'created_by' => $this->staffPurchasing->id,
    ]);

    $orItem = OrderRequestItem::create([
        'order_request_id' => $or->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'product_id' => $this->product->id,
        'quantity' => 10, // Kuantitas di OR hanya 10
        'fulfilled_quantity' => 0,
        'status' => 'approved',
    ]);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-EXCEED-OR-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'total_amount' => 7500000,
        'created_by' => $this->staffPurchasing->id,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'refer_item_model_type' => OrderRequestItem::class,
        'refer_item_model_id' => $orItem->id,
        'quantity' => 15, // 15 Melebihi batas OR (10)
        'unit_price' => 500000,
        'subtotal' => 7500000,
    ]);

    // Direktur mencoba approve, tetapi terkunci oleh OrderRequestQuantityLock
    $response = $this->actingAs($this->ownerUser)->post(route('pembelian.po.approve', $po->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $errorMessage = session('error');
    expect($errorMessage)->toContain('tidak boleh melebihi sisa Order Request');
    expect($po->fresh()->status)->toBe('draft');
});

test('UAT-PO-01: Multi-Level Purchase Order approval flow with 4-Layer zero-leak validation', function () {
    // -------------------------------------------------------------
    // ALUR 1: PO Nilai Menengah (Rp 5.000.000 <= Rp 10.000.000)
    // -------------------------------------------------------------
    $poMedium = PurchaseOrder::create([
        'po_number' => 'PO-UAT-MED-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'total_amount' => 5000000,
        'created_by' => $this->staffPurchasing->id,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $poMedium->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 10,
        'unit_price' => 500000,
        'subtotal' => 5000000,
    ]);

    // Purchasing Manager menyetujui PO medium (Rp 5.000.000)
    $resMedium = $this->actingAs($this->purchasingManager)->post(route('pembelian.po.approve', $poMedium->id));
    $resMedium->assertRedirect();
    $resMedium->assertSessionHas('success');

    $poMedium->refresh();
    expect($poMedium->status)->toBe('approved');
    expect((int) $poMedium->approved_by)->toBe($this->purchasingManager->id);
    expect($poMedium->date_approved)->not->toBeNull();

    // -------------------------------------------------------------
    // ALUR 2: PO Nilai Tinggi (Rp 25.000.000 > Rp 10.000.000)
    // -------------------------------------------------------------
    $poHigh = PurchaseOrder::create([
        'po_number' => 'PO-UAT-HIGH-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'total_amount' => 25000000,
        'created_by' => $this->staffPurchasing->id,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $poHigh->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 50,
        'unit_price' => 500000,
        'subtotal' => 25000000,
    ]);

    // Direktur / Owner menyetujui PO High (Rp 25.000.000)
    $resHigh = $this->actingAs($this->ownerUser)->post(route('pembelian.po.approve', $poHigh->id));
    $resHigh->assertRedirect();
    $resHigh->assertSessionHas('success');

    $poHigh->refresh();
    expect($poHigh->status)->toBe('approved');
    expect((int) $poHigh->approved_by)->toBe($this->ownerUser->id);
    expect($poHigh->date_approved)->not->toBeNull();

    // -------------------------------------------------------------
    // PEMBUKTIAN INTEGRITAS DATA 4-LAPIS (Zero-Leak Proof)
    // -------------------------------------------------------------
    // LAYER 1: Dokumen Sah & Audit Trail Tercatat
    expect($poMedium->status)->toBe('approved');
    expect($poHigh->status)->toBe('approved');

    // LAYER 2: Kartu Stok Fisik Belum Berubah (PO adalah Komitmen Pesanan, Belum Diterima QC/GRN)
    // Saldo fisik tetap 50.0 (tidak ada phantom stock bertambah secara prematur)
    expect((float) $this->product->inventoryStock()->sum('qty_available'))->toBe(50.0);

    // LAYER 3: Jurnal Akuntansi Buku Besar (Belum Ada Jurnal Hutang/Persediaan Prematur)
    // Sesuai prinsip akuntansi, PO bukan transaksi pengakuan utang sebelum barang diterima/faktur terbit
    $poJournals = JournalEntry::where('source_type', PurchaseOrder::class)
        ->whereIn('source_id', [$poMedium->id, $poHigh->id])
        ->get();
    expect($poJournals->count())->toBe(0);

    // LAYER 4: Kartu Hutang Usaha (AP Sub-Ledger) Bersih dari Pengakuan Dini
    expect($poMedium->invoice()->count())->toBe(0);
    expect($poHigh->invoice()->count())->toBe(0);
});
