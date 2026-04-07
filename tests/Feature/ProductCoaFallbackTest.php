<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('purchase receipt posting falls back to default coa when product coa mappings are missing', function () {
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'manage_type' => 'all',
    ]);

    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $product = Product::factory()->create([
        'cabang_id' => $cabang->id,
        'inventory_coa_id' => null,
        'unbilled_purchase_coa_id' => null,
        'temporary_procurement_coa_id' => null,
        'purchase_return_coa_id' => null,
        'cogs_coa_id' => null,
        'goods_delivery_coa_id' => null,
    ]);

    ChartOfAccount::firstOrCreate(['code' => '1140.01'], ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '2100.10'], ['name' => 'Hutang Pembelian Belum Ditagih', 'type' => 'Liability', 'is_active' => true]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'approved',
        'created_by' => $user->id,
        'cabang_id' => $cabang->id,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 25000,
    ]);

    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RN-FALLBACK-001',
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
        'rak_id' => null,
        'qty_received' => 3,
        'qty_accepted' => 3,
        'qty_rejected' => 0,
        'status' => 'completed',
    ]);

    $result = app(PurchaseReceiptService::class)->postItemInventoryAfterQC($receiptItem);

    expect($result['status'])->toBe('posted');
    expect(InventoryStock::freeQtyFor($product->id, $warehouse->id, null))->toBe(3.0);
});