<?php

use App\Models\User;
use App\Models\PurchaseReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\JournalEntry;
use App\Models\Cabang;
use App\Services\PurchaseReturnService;
use App\Services\StockService;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses()->group('purchase-return');
uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed other required data (but not suppliers yet, as they need cabang)
    test()->seed(\Database\Seeders\CurrencySeeder::class);
    test()->seed(\Database\Seeders\UnitOfMeasureSeeder::class);
    test()->seed(\Database\Seeders\ProductSeeder::class);
    test()->seed(\Database\Seeders\WarehouseSeeder::class);
});

test('can create purchase return with auto generated number', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);
    $service = app(PurchaseReturnService::class);

    // Get the first cabang ID
    $cabangId = Cabang::first()->id;

    // Create a purchase order first (manually to avoid factory issues)
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'completed',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => rand(50000, 2000000),
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'is_asset' => rand(0, 1),
        'close_reason' => null,
        'date_approved' => now(),
        'approved_by' => 1,
        'warehouse_id' => 1,
        'tempo_hutang' => rand(0, 60),
        'note' => null,
        'close_requested_by' => 1,
        'close_requested_at' => now(),
        'closed_by' => 1,
        'closed_at' => now(),
        'completed_by' => 1,
        'completed_at' => now(),
        'refer_model_type' => null,
        'refer_model_id' => null,
        'is_import' => false,
        'ppn_option' => 'standard',
    ]);

    // Create purchase receipt
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now()->subDays(rand(1, 7)),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => $purchaseOrder->total_amount,
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'received_by' => $user->id,
        'currency_id' => 1,
    ]);

    $purchaseReturn = $service->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'return_date' => now(),
    ]);

    expect($purchaseReturn->nota_retur)->toMatch('/^NR-\d{8}-\d{4}$/')
        ->and($purchaseReturn->purchase_receipt_id)->toBe($purchaseReceipt->id)
        ->and($purchaseReturn->cabang_id)->toBe($user->cabang_id);
});

test('can submit purchase return for approval', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);
    $service = app(PurchaseReturnService::class);

    // Create a purchase order first (manually to avoid factory issues)
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'completed',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => rand(50000, 2000000),
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'is_asset' => rand(0, 1),
        'close_reason' => null,
        'date_approved' => now(),
        'approved_by' => 1,
        'warehouse_id' => 1,
        'tempo_hutang' => rand(0, 60),
        'note' => null,
        'close_requested_by' => 1,
        'close_requested_at' => now(),
        'closed_by' => 1,
        'closed_at' => now(),
        'completed_by' => 1,
        'completed_at' => now(),
        'refer_model_type' => null,
        'refer_model_id' => null,
        'is_import' => false,
        'ppn_option' => 'standard',
    ]);

    // Create purchase receipt
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now()->subDays(rand(1, 7)),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => $purchaseOrder->total_amount,
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
    ]);

    $purchaseReturn = $service->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0001',
    ]);

    $result = $service->submitForApproval($purchaseReturn);

    expect($result)->toBeTrue()
        ->and($purchaseReturn->fresh()->status)->toBe('pending_approval');
});

test('cannot submit non-draft purchase return', function () {
    // Create cabang manually for this test
    Cabang::create([
        'id' => 1,
        'kode' => 'CBG-001',
        'nama' => 'Cabang Utama',
        'alamat' => 'Jl. Utama No. 1',
        'telepon' => '021-123456',
        'kenaikan_harga' => 0,
        'status' => 1,
        'warna_background' => '#ffffff',
        'tipe_penjualan' => 'Pajak',
        'kode_invoice_pajak' => 'INV-PJK-001',
        'kode_invoice_non_pajak' => 'INV-NPJK-001',
        'kode_invoice_pajak_walkin' => 'INV-WPJK-001',
        'nama_kwitansi' => 'Kwitansi Utama',
        'label_invoice_pajak' => 'Pajak',
        'label_invoice_non_pajak' => 'Non Pajak',
        'logo_invoice_non_pajak' => null,
        'lihat_stok_cabang_lain' => 0,
    ]);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);
    $service = app(PurchaseReturnService::class);

    // Create a purchase order first (manually to avoid factory issues)
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'completed',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => rand(50000, 2000000),
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'is_asset' => rand(0, 1),
        'close_reason' => null,
        'date_approved' => now(),
        'approved_by' => 1,
        'warehouse_id' => 1,
        'tempo_hutang' => rand(0, 60),
        'note' => null,
        'close_requested_by' => 1,
        'close_requested_at' => now(),
        'closed_by' => 1,
        'closed_at' => now(),
        'completed_by' => 1,
        'completed_at' => now(),
        'refer_model_type' => null,
        'refer_model_id' => null,
        'is_import' => false,
        'ppn_option' => 'standard',
    ]);

    // Create purchase receipt
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now()->subDays(rand(1, 7)),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => $purchaseOrder->total_amount,
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
    ]);

    $purchaseReturn = $service->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0002',
        'status' => 'approved',
    ]);

    $service->submitForApproval($purchaseReturn);
})->throws(\Exception::class);

test('can approve pending purchase return', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);
    $service = app(PurchaseReturnService::class);

    // Create a purchase order first (manually to avoid factory issues)
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'completed',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => rand(50000, 2000000),
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'is_asset' => rand(0, 1),
        'close_reason' => null,
        'date_approved' => now(),
        'approved_by' => 1,
        'warehouse_id' => 1,
        'tempo_hutang' => rand(0, 60),
        'note' => null,
        'close_requested_by' => 1,
        'close_requested_at' => now(),
        'closed_by' => 1,
        'closed_at' => now(),
        'completed_by' => 1,
        'completed_at' => now(),
        'refer_model_type' => null,
        'refer_model_id' => null,
        'is_import' => false,
        'ppn_option' => 'standard',
    ]);

    // Create purchase receipt
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now()->subDays(rand(1, 7)),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => $purchaseOrder->total_amount,
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
    ]);

    $purchaseReturn = $service->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0003',
    ]);

    $service->submitForApproval($purchaseReturn);

    $result = $service->approve($purchaseReturn, ['approval_notes' => 'Approved']);

    expect($result)->toBeTrue()
        ->and($purchaseReturn->fresh()->status)->toBe('approved');
});

test('can reject pending purchase return', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);
    $service = app(PurchaseReturnService::class);

    // Create a purchase order first (manually to avoid factory issues)
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'completed',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => rand(50000, 2000000),
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'is_asset' => rand(0, 1),
        'close_reason' => null,
        'date_approved' => now(),
        'approved_by' => 1,
        'warehouse_id' => 1,
        'tempo_hutang' => rand(0, 60),
        'note' => null,
        'close_requested_by' => 1,
        'close_requested_at' => now(),
        'closed_by' => 1,
        'closed_at' => now(),
        'completed_by' => 1,
        'completed_at' => now(),
        'refer_model_type' => null,
        'refer_model_id' => null,
        'is_import' => false,
        'ppn_option' => 'standard',
    ]);

    // Create purchase receipt
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now()->subDays(rand(1, 7)),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => $purchaseOrder->total_amount,
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
    ]);

    $purchaseReturn = $service->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0004',
        'status' => 'pending_approval',
    ]);

    $result = $service->reject($purchaseReturn, ['rejection_notes' => 'Not approved']);

    expect($result)->toBeTrue()
        ->and($purchaseReturn->fresh()->status)->toBe('rejected');
});

test('stock adjustment on approval', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);
    $service = app(PurchaseReturnService::class);

    // Create a purchase order first (manually to avoid factory issues)
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'completed',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => rand(50000, 2000000),
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'is_asset' => rand(0, 1),
        'close_reason' => null,
        'date_approved' => now(),
        'approved_by' => 1,
        'warehouse_id' => 1,
        'tempo_hutang' => rand(0, 60),
        'note' => null,
        'close_requested_by' => 1,
        'close_requested_at' => now(),
        'closed_by' => 1,
        'closed_at' => now(),
        'completed_by' => 1,
        'completed_at' => now(),
        'refer_model_type' => null,
        'refer_model_id' => null,
        'is_import' => false,
        'ppn_option' => 'standard',
    ]);

    // Create purchase receipt
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now()->subDays(rand(1, 7)),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => $purchaseOrder->total_amount,
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
    ]);

    $purchaseReturn = $service->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0005',
        'status' => 'approved',
    ]);

    $result = $service->adjustStock($purchaseReturn);

    expect($result)->toBeTrue();
});

test('journal entry creation on approval', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);
    $service = app(PurchaseReturnService::class);

    // Create a purchase order first (manually to avoid factory issues)
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'completed',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => rand(50000, 2000000),
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
        'is_asset' => rand(0, 1),
        'close_reason' => null,
        'date_approved' => now(),
        'approved_by' => 1,
        'warehouse_id' => 1,
        'tempo_hutang' => rand(0, 60),
        'note' => null,
        'close_requested_by' => 1,
        'close_requested_at' => now(),
        'closed_by' => 1,
        'closed_at' => now(),
        'completed_by' => 1,
        'completed_at' => now(),
        'refer_model_type' => null,
        'refer_model_id' => null,
        'is_import' => false,
        'ppn_option' => 'standard',
    ]);

    // Create purchase receipt
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now()->subDays(rand(1, 7)),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => $purchaseOrder->total_amount,
        'cabang_id' => Cabang::first()->id,
        'currency_id' => 1,
        'created_by' => $user->id,
    ]);

    $purchaseReturn = $service->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0006',
        'status' => 'approved',
    ]);

    $result = $service->createJournalEntry($purchaseReturn);

    expect($result)->toBeTrue();
});

test('purchase return auto created for rejected items in receipt', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);

    // Create purchase order
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'approved',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => 1000000,
        'cabang_id' => Cabang::first()->id,
        'warehouse_id' => 1,
        'tempo_hutang' => 30,
        'created_by' => $user->id,
        'approved_by' => $user->id,
        'date_approved' => now(),
    ]);

    // Create PO item
    $poItem = \App\Models\PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => 1,
        'quantity' => 10,
        'unit_price' => 100000,
        'subtotal' => 1000000,
        'currency_id' => 1,
    ]);

    // Create purchase receipt with rejected items
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RN-' . now()->format('Ymd') . '-001',
        'receipt_date' => now(),
        'cabang_id' => Cabang::first()->id,
        'received_by' => $user->id,
        'total_received' => 1000000,
        'currency_id' => 1,
    ]);

    // Create receipt item with rejected quantity
    $receiptItem = \App\Models\PurchaseReceiptItem::create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => 1,
        'qty_received' => 10,
        'qty_accepted' => 8,
        'qty_rejected' => 2, // This should trigger auto creation of PurchaseReturn
        'warehouse_id' => 1,
        'reason_rejected' => 'Damaged during transport',
    ]);

    // Debug: Verify receipt item was created
    expect(\App\Models\PurchaseReceiptItem::where('purchase_receipt_id', $purchaseReceipt->id)->exists())->toBeTrue();
    expect(\App\Models\PurchaseReceiptItem::where('purchase_receipt_id', $purchaseReceipt->id)->count())->toBe(1);

    // Assert: PurchaseReturn should be auto-created
    expect(PurchaseReturn::where('purchase_receipt_id', $purchaseReceipt->id)->exists())->toBeTrue();

    $purchaseReturn = PurchaseReturn::where('purchase_receipt_id', $purchaseReceipt->id)->first();
    expect($purchaseReturn->nota_retur)->toStartWith('NR-');
    expect($purchaseReturn->created_by)->toBe($user->id);
    expect($purchaseReturn->status)->toBe('draft');
    expect($purchaseReturn->notes)->toBe('Auto-generated return for items rejected during receiving');

    // Assert: PurchaseReturnItem should be created
    expect($purchaseReturn->purchaseReturnItem)->toHaveCount(1);
    $returnItem = $purchaseReturn->purchaseReturnItem->first();
    expect($returnItem->purchase_receipt_item_id)->toBe($receiptItem->id);
    expect($returnItem->product_id)->toBe(1);
    expect($returnItem->qty_returned)->toBe('2.00');
    expect($returnItem->unit_price)->toBe('100000.00');
    expect($returnItem->reason)->toBe('Rejected during receiving: Damaged during transport');
});

test('purchase return not created when no rejected items', function () {
    // Seed cabang data
    test()->seed(\Database\Seeders\CabangSeeder::class);

    // Now seed suppliers after cabang exists
    test()->seed(\Database\Seeders\SupplierSeeder::class);

    $user = User::factory()->create(['cabang_id' => 1]);
    test()->actingAs($user);

    // Create purchase order
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => 1,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now()->subDays(rand(1, 30)),
        'status' => 'approved',
        'received_by' => $user->id,
        'expected_date' => now()->addDays(rand(3, 14)),
        'total_amount' => 1000000,
        'cabang_id' => Cabang::first()->id,
        'created_by' => $user->id,
        'approved_by' => $user->id,
        'date_approved' => now(),
    ]);

    // Create PO item
    $poItem = \App\Models\PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => 1,
        'quantity' => 10,
        'unit_price' => 100000,
        'subtotal' => 1000000,
    ]);

    // Create purchase receipt with NO rejected items
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RN-' . now()->format('Ymd') . '-002',
        'receipt_date' => now(),
        'cabang_id' => Cabang::first()->id,
        'created_by' => $user->id,
        'total_received' => 1000000,
        'currency_id' => 1,
    ]);

    // Create receipt item with NO rejected quantity
    $receiptItem = \App\Models\PurchaseReceiptItem::create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => 1,
        'qty_received' => 10,
        'qty_accepted' => 10,
        'qty_rejected' => 0, // No rejected items
        'warehouse_id' => 1,
    ]);

    // Assert: PurchaseReturn should NOT be created
    expect(PurchaseReturn::where('purchase_receipt_id', $purchaseReceipt->id)->exists())->toBeFalse();
});