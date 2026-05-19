<?php

use App\Filament\Resources\PurchaseReceiptResource\Pages\ViewPurchaseReceipt;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\QualityControl;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['view any purchase receipt', 'view purchase receipt'] as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }
});

test('purchase receipt view shows related journal entries from receipt and receipt item sources', function () {
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'manage_type' => 'all',
    ]);
    $user->givePermissionTo(['view any purchase receipt', 'view purchase receipt']);
    $this->actingAs($user);

    $supplier = Supplier::factory()->create([
        'perusahaan' => 'Supplier View Test',
    ]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $product = Product::factory()->create([
        'cabang_id' => $cabang->id,
    ]);

    $inventoryCoa = ChartOfAccount::firstOrCreate(['code' => '1140.01'], ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]);
    $temporaryProcurementCoa = ChartOfAccount::firstOrCreate(['code' => '1400.01'], ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]);
    $unbilledPurchaseCoa = ChartOfAccount::firstOrCreate(['code' => '2100.10'], ['name' => 'Hutang Pembelian Belum Ditagih', 'type' => 'Liability', 'is_active' => true]);

    $product->update([
        'inventory_coa_id' => $inventoryCoa->id,
        'temporary_procurement_coa_id' => $temporaryProcurementCoa->id,
        'unbilled_purchase_coa_id' => $unbilledPurchaseCoa->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'approved',
        'created_by' => $user->id,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 10000,
    ]);

    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RN-VIEW-001',
        'receipt_date' => now(),
        'received_by' => $user->id,
        'currency_id' => $currency->id,
        'status' => 'completed',
        'cabang_id' => $cabang->id,
    ]);

    $receiptItem = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty_received' => 5,
        'qty_accepted' => 5,
        'qty_rejected' => 0,
        'status' => 'completed',
    ]);

    $qualityControl = QualityControl::factory()->create([
        'qc_number' => 'QC-VIEW-001',
        'inspected_by' => $user->id,
        'passed_quantity' => 5,
        'rejected_quantity' => 0,
        'notes' => 'QC purchase receipt test',
        'status' => 1,
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'from_model_type' => PurchaseReceiptItem::class,
        'from_model_id' => $receiptItem->id,
        'cabang_id' => $cabang->id,
    ]);

    $service = app(PurchaseReceiptService::class);
    $temporaryResult = $service->createTemporaryProcurementEntriesForReceiptItem($receiptItem);
    expect($temporaryResult['status'])->toBe('posted');

    $inventoryResult = $service->postItemInventoryAfterQC($receiptItem);
    expect($inventoryResult['status'])->toBe('posted');

    $zeroOutResult = $service->zeroOutTemporaryProcurementPositions($receipt);
    expect($zeroOutResult['status'])->toBe('posted');

    Livewire::actingAs($user)
        ->test(ViewPurchaseReceipt::class, ['record' => $receipt->getKey()])
        ->assertSee('Informasi Purchase Receipt')
        ->assertSee('Supplier View Test')
        ->assertSee($receipt->receipt_date->format('d M Y H:i'))
        ->assertSee('Detail Produk dan QC Purchase')
        ->assertSee('QC-VIEW-001')
        ->assertSee('QC purchase receipt test')
        ->assertSee($product->name)
        ->assertSee('Jurnal Penerimaan Barang')
        ->assertSee('Related Journal Entries')
        ->assertSee('Penerimaan Barang')
        ->assertSee('Item #')
        ->assertSee('Temporary Procurement')
        ->assertSee('Zero out temporary procurement positions')
        ->assertSee('Temporary Procurement - Item sent to QC');

    expect(JournalEntry::query()
        ->where('source_type', PurchaseReceipt::class)
        ->where('source_id', $receipt->id)
        ->exists())->toBeTrue();

    expect(JournalEntry::query()
        ->where('source_type', PurchaseReceiptItem::class)
        ->where('source_id', $receiptItem->id)
        ->exists())->toBeTrue();
});

test('purchase receipt view falls back to purchase order item qc when receipt item qc is absent', function () {
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'manage_type' => 'all',
    ]);
    $user->givePermissionTo(['view any purchase receipt', 'view purchase receipt']);
    $this->actingAs($user);

    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $product = Product::factory()->forCabang($cabang)->create();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'approved',
        'created_by' => $user->id,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 10000,
    ]);

    QualityControl::factory()->create([
        'qc_number' => 'QC-PO-001',
        'inspected_by' => $user->id,
        'passed_quantity' => 5,
        'rejected_quantity' => 0,
        'notes' => 'QC from PO item',
        'status' => 1,
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'from_model_type' => PurchaseOrderItem::class,
        'from_model_id' => $poItem->id,
        'cabang_id' => $cabang->id,
    ]);

    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RN-VIEW-PO-QC-001',
        'receipt_date' => now(),
        'received_by' => $user->id,
        'currency_id' => $currency->id,
        'status' => 'completed',
        'cabang_id' => $cabang->id,
    ]);

    PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty_received' => 5,
        'qty_accepted' => 5,
        'qty_rejected' => 0,
        'status' => 'completed',
    ]);

    Livewire::actingAs($user)
        ->test(ViewPurchaseReceipt::class, ['record' => $receipt->getKey()])
        ->assertSee('QC-PO-001')
        ->assertSee('QC from PO item')
        ->assertDontSee('Belum ada QC purchase.');
});