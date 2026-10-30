<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    // Role Definitions
    $this->roleChecker = Role::firstOrCreate(['name' => 'Checker Gudang', 'guard_name' => 'web']);
    $this->rolePurchasing = Role::firstOrCreate(['name' => 'Admin Pengadaan', 'guard_name' => 'web']);

    $this->checkerUser = User::factory()->create([
        'name' => 'Anto Checker Gudang & QC',
        'cabang_id' => $this->cabang->id,
    ]);
    $this->checkerUser->assignRole($this->roleChecker);

    $this->purchasingUser = User::factory()->create([
        'name' => 'Rina Admin Pengadaan',
        'cabang_id' => $this->cabang->id,
    ]);
    $this->purchasingUser->assignRole($this->rolePurchasing);

    // Setup Akun COA Standar Pengadaan & Persediaan
    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        [
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'currency_id' => $this->currency->id,
            'cabang_id' => $this->cabang->id,
        ]
    );

    $this->invCoa      = $coa('1140.10', 'Persediaan Barang Dagangan', 'Asset');
    $this->unbilledCoa = $coa('2100.10', 'Pembelian Belum Tertagih', 'Liability');
    $this->apCoa       = $coa('2110', 'Hutang Dagang', 'Liability');

    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'kode' => 'WH-UTM',
        'name' => 'Gudang Logistik Utama',
    ]);

    $this->supplier = Supplier::factory()->create([
        'cabang_id' => $this->cabang->id,
        'code' => 'SUP-BAJA-01',
        'perusahaan' => 'PT Krakatau Steel Industri',
        'tempo_hutang' => 30,
    ]);

    $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Bahan Baku Logam', 'kode' => 'MET']);
    $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Batang', 'abbreviation' => 'btg']);

    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'name' => 'Pipa Stainless 304 2 Inch',
        'cost_price' => 100000,
        'sell_price' => 150000,
        'inventory_coa_id' => $this->invCoa->id,
        'unbilled_purchase_coa_id' => $this->unbilledCoa->id,
        'is_active' => true,
    ]);
});

test('ADV-QC-01: Conservation Violation (Passed + Rejected exceeding Received) is strictly rejected', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-CONS-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 2000000,
        'created_by' => $this->purchasingUser->id,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 20,
        'unit_price' => 100000,
        'subtotal' => 2000000,
    ]);

    // Kuantitas tiba = 15, tetapi Checker memasukkan Passed 12 + Rejected 5 = 17 (> 15)
    $payload = [
        'purchase_order_id' => $po->id,
        'receipt_date' => now()->toDateString(),
        'action' => 'save_and_confirm',
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'qty_received' => 15,
                'qty_accepted' => 12,
                'qty_rejected' => 5, // 12 + 5 = 17 > 15
                'reason_rejected' => 'Pipa penyok',
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.penerimaan.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['items.0.qty_accepted']);
    expect(PurchaseReceipt::count())->toBe(0);
    // Stok fisik tidak bertambah
    expect((float) $this->product->inventoryStock()->sum('qty_available'))->toBe(0.0);
});

test('ADV-QC-02: Missing reason when rejecting items in QC is strictly rejected', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-REASON-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1000000,
        'created_by' => $this->purchasingUser->id,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 10,
        'unit_price' => 100000,
        'subtotal' => 1000000,
    ]);

    // Checker menolak 3 pcs tapi mengosongkan alasan penolakan
    $payload = [
        'purchase_order_id' => $po->id,
        'receipt_date' => now()->toDateString(),
        'action' => 'save_and_confirm',
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'qty_received' => 10,
                'qty_accepted' => 7,
                'qty_rejected' => 3,
                'reason_rejected' => '', // Kosong!
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.penerimaan.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['items.0.reason_rejected']);
    expect(PurchaseReceipt::count())->toBe(0);
});

test('ADV-QC-03: Over-Receipt Protection blocks receiving quantity exceeding PO remaining limit', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-OVER-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1000000,
        'created_by' => $this->purchasingUser->id,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 10, // Target PO hanya 10
        'unit_price' => 100000,
        'subtotal' => 1000000,
    ]);

    // Checker mencoba menerima 15 unit (over-receipt)
    $payload = [
        'purchase_order_id' => $po->id,
        'receipt_date' => now()->toDateString(),
        'action' => 'save_and_confirm',
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'qty_received' => 15, // Melebihi 10
                'qty_accepted' => 15,
                'qty_rejected' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.penerimaan.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['items.0.qty_received']);
    expect(PurchaseReceipt::count())->toBe(0);
});

test('ADV-QC-04: Immutability protection rejects edit or delete on confirmed goods receipt', function () {
    $po = PurchaseOrder::create([
        'po_number' => 'PO-LOCK-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 1000000,
        'created_by' => $this->purchasingUser->id,
    ]);

    $receipt = PurchaseReceipt::create([
        'receipt_number' => 'GRN-LOCK-' . uniqid(),
        'purchase_order_id' => $po->id,
        'receipt_date' => now()->toDateString(),
        'status' => 'completed', // Sudah diverifikasi dan masuk stok
        'currency_id' => $this->currency->id,
        'cabang_id' => $this->cabang->id,
        'received_by' => $this->checkerUser->id,
    ]);

    // 1. Coba Edit penerimaan yang sudah completed
    $editResponse = $this->actingAs($this->checkerUser)->get(route('gudang.penerimaan.edit', $receipt->id));
    $editResponse->assertRedirect();
    $editResponse->assertSessionHas('error');

    // 2. Coba Hapus penerimaan yang sudah completed
    $delResponse = $this->actingAs($this->checkerUser)->delete(route('gudang.penerimaan.destroy', $receipt->id));
    $delResponse->assertRedirect();
    $delResponse->assertSessionHas('error');
    expect(PurchaseReceipt::where('id', $receipt->id)->exists())->toBeTrue();
});

test('UAT-QC-01: Full Receiving Flow with QC Discrepancy, Auto-Return, and 4-Layer Validation', function () {
    // LANGKAH 1: PO resmi disetujui untuk 20 unit @ Rp 100.000 = Rp 2.000.000
    $po = PurchaseOrder::create([
        'po_number' => 'PO-QC-UAT-' . uniqid(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'currency_id' => $this->currency->id,
        'order_date' => now()->toDateString(),
        'status' => 'approved',
        'total_amount' => 2000000,
        'created_by' => $this->purchasingUser->id,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'currency_id' => $this->currency->id,
        'quantity' => 20,
        'unit_price' => 100000,
        'subtotal' => 2000000,
    ]);

    // LANGKAH 2: Checker menerima fisik 20 unit, QC Check: 15 Lolos, 5 Rusak Fisik
    $payload = [
        'purchase_order_id' => $po->id,
        'receipt_date' => now()->toDateString(),
        'supplier_delivery_note_number' => 'SJ-VENDOR-9988',
        'notes' => 'Penerimaan fisik dengan temuan pipa bengkok & korosi di pintu gudang',
        'action' => 'save_and_confirm',
        'items' => [
            [
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'qty_received' => 20,
                'qty_accepted' => 15, // Lolos QC
                'qty_rejected' => 5,  // Cacat / Ditolak
                'reason_rejected' => 'Batang bengkok dan korosi pada ujung sambungan',
            ],
        ],
    ];

    $response = $this->actingAs($this->checkerUser)->post(route('gudang.penerimaan.store'), $payload);
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $receipt = PurchaseReceipt::latest()->first();

    // -------------------------------------------------------------
    // PEMBUKTIAN INTEGRITAS DATA 4-LAPIS (4-Layer Proof)
    // -------------------------------------------------------------

    // LAYER 1: Dokumen Penerimaan Sah, QC Record Terbit, Draft Retur Terbentuk Otomatis
    expect($receipt)->not->toBeNull();
    expect($receipt->status)->toBe('completed');
    expect($receipt->purchase_order_id)->toBe($po->id);

    $receiptItem = $receipt->purchaseReceiptItem->first();
    expect((float) $receiptItem->qty_received)->toBe(20.0);
    expect((float) $receiptItem->qty_accepted)->toBe(15.0);
    expect((float) $receiptItem->qty_rejected)->toBe(5.0);

    // Dokumen QC terbentuk otomatis
    $qc = QualityControl::where('from_model_type', PurchaseReceipt::class)
        ->where('from_model_id', $receipt->id)
        ->first();
    expect($qc)->not->toBeNull();
    expect((float) $qc->passed_quantity)->toBe(15.0);
    expect((float) $qc->rejected_quantity)->toBe(5.0);
    expect($qc->reason_reject)->toContain('Batang bengkok');

    // Dokumen Draft Retur Pembelian terbentuk otomatis
    $return = PurchaseReturn::where('purchase_receipt_id', $receipt->id)->first();
    expect($return)->not->toBeNull();
    expect($return->status)->toBe('draft');
    $returnItem = $return->purchaseReturnItems->first();
    expect($returnItem)->not->toBeNull();
    expect((float) $returnItem->qty_returned)->toBe(5.0);

    // Status PO bertransisi ke partially_received (karena baru 15 dari 20 yang diterima bersih)
    expect($po->fresh()->status)->toBe('partially_received');

    // LAYER 2: Kartu Stok Fisik Gudang
    // Masuk stok bersih yang lolos QC = tepat 15.0 batang. Barang rusak (5.0) TIDAK BOLEH masuk stok!
    expect((float) $this->product->inventoryStock()->sum('qty_available'))->toBe(15.0);

    // LAYER 3: Jurnal Akuntansi Buku Besar Pengadaan (Zero Balance Drift)
    // Debit Persediaan (1140.10) vs Kredit Pembelian Belum Tertagih / Unbilled AP (2100.10)
    $journals = JournalEntry::where('source_type', PurchaseReceiptItem::class)
        ->where('source_id', $receiptItem->id)
        ->get();

    expect($journals->count())->toBe(2);
    $totalDebit = (float) $journals->sum('debit');
    $totalCredit = (float) $journals->sum('credit');

    // Nilai = 15 unit lolos x Rp 100.000 = Rp 1.500.000
    expect($totalDebit)->toBe($totalCredit);
    expect($totalDebit)->toBe(1500000.0);

    $invJournal = $journals->where('coa_id', $this->invCoa->id)->first();
    expect($invJournal)->not->toBeNull();
    expect((float) $invJournal->debit)->toBe(1500000.0);

    $unbilledJournal = $journals->where('coa_id', $this->unbilledCoa->id)->first();
    expect($unbilledJournal)->not->toBeNull();
    expect((float) $unbilledJournal->credit)->toBe(1500000.0);

    // LAYER 4: Finansial / Hutang Dagang Resmi Belum Diakui (Menunggu Faktur Vendor di Modul 08)
    expect($po->invoice()->count())->toBe(0);
});
