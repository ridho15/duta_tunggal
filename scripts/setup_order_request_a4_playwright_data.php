<?php
/**
 * Deterministic fixture for A4 (OrderRequest status colors + transition checks).
 *
 * Creates:
 * - OR-TEST-A4-REQAPP   status=request_approve
 * - OR-TEST-A4-APPROVED status=approved
 * - OR-TEST-A4-CLOSED   status=closed
 * - OR-TEST-A4-REJECTED status=rejected
 * - OR-TEST-A4-PARTIAL  status transitions to partial via PurchaseOrderService::approvePo()
 * - OR-TEST-A4-COMPLETE status transitions to complete via PurchaseOrderService::approvePo()
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;

$now = now();

DB::transaction(function () use ($now) {
    $testUser = DB::table('users')->where('email', 'ralamzah@gmail.com')->first();
    $userId = $testUser?->id ?? DB::table('users')->value('id') ?? 1;
    $cabangId = $testUser?->cabang_id ?? DB::table('cabangs')->value('id') ?? 1;

    $warehouseId = DB::table('warehouses')->where('cabang_id', $cabangId)->value('id')
        ?? DB::table('warehouses')->value('id')
        ?? 1;
    $supplierId = DB::table('suppliers')->value('id') ?? 1;
    $currencyId = DB::table('currencies')->value('id') ?? 1;
    $productIds = DB::table('products')->orderBy('id')->limit(2)->pluck('id')->toArray();
    $productA = $productIds[0] ?? 1;
    $productB = $productIds[1] ?? $productA;

    $prefix = 'OR-TEST-A4-';

    // Cleanup old fixtures
    $oldOrIds = DB::table('order_requests')->where('request_number', 'like', $prefix . '%')->pluck('id')->toArray();
    if (!empty($oldOrIds)) {
        $oldPoIds = DB::table('purchase_orders')
            ->where('refer_model_type', 'App\\Models\\OrderRequest')
            ->whereIn('refer_model_id', $oldOrIds)
            ->pluck('id')
            ->toArray();

        if (!empty($oldPoIds)) {
            DB::table('purchase_order_items')->whereIn('purchase_order_id', $oldPoIds)->delete();
            DB::table('purchase_orders')->whereIn('id', $oldPoIds)->delete();
        }

        DB::table('order_request_items')->whereIn('order_request_id', $oldOrIds)->delete();
        DB::table('order_requests')->whereIn('id', $oldOrIds)->delete();
    }

    $createOr = function (string $requestNumber, string $status) use ($warehouseId, $cabangId, $userId, $now) {
        return DB::table('order_requests')->insertGetId([
            'request_number' => $requestNumber,
            'warehouse_id' => $warehouseId,
            'cabang_id' => $cabangId,
            'request_date' => now()->toDateString(),
            'status' => $status,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    };

    // Static status fixtures
    $orReqAppId = $createOr('OR-TEST-A4-REQAPP', 'request_approve');
    $orApprovedId = $createOr('OR-TEST-A4-APPROVED', 'approved');
    $orClosedId = $createOr('OR-TEST-A4-CLOSED', 'closed');
    $orRejectedId = $createOr('OR-TEST-A4-REJECTED', 'rejected');

    foreach ([$orReqAppId, $orApprovedId, $orClosedId, $orRejectedId] as $orId) {
        DB::table('order_request_items')->insert([
            'order_request_id' => $orId,
            'product_id' => $productA,
            'supplier_id' => $supplierId,
            'quantity' => 5,
            'fulfilled_quantity' => 0,
            'unit_price' => 100000,
            'original_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'subtotal' => 500000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $service = app(PurchaseOrderService::class);

    // Transition fixture 1: approved -> partial after approvePo
    $orPartial = OrderRequest::create([
        'request_number' => 'OR-TEST-A4-PARTIAL',
        'warehouse_id' => $warehouseId,
        'cabang_id' => $cabangId,
        'request_date' => now()->toDateString(),
        'status' => 'approved',
        'created_by' => $userId,
    ]);

    $orPartialItemA = OrderRequestItem::create([
        'order_request_id' => $orPartial->id,
        'product_id' => $productA,
        'supplier_id' => $supplierId,
        'quantity' => 10,
        'fulfilled_quantity' => 0,
        'unit_price' => 100000,
        'original_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'subtotal' => 1000000,
    ]);

    $orPartialItemB = OrderRequestItem::create([
        'order_request_id' => $orPartial->id,
        'product_id' => $productB,
        'supplier_id' => $supplierId,
        'quantity' => 10,
        'fulfilled_quantity' => 0,
        'unit_price' => 120000,
        'original_price' => 120000,
        'discount' => 0,
        'tax' => 0,
        'subtotal' => 1200000,
    ]);

    $poPartial = PurchaseOrder::create([
        'supplier_id' => $supplierId,
        'po_number' => 'PO-TEST-A4-PARTIAL',
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'warehouse_id' => $warehouseId,
        'tempo_hutang' => 30,
        'created_by' => $userId,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orPartial->id,
        'cabang_id' => $cabangId,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $poPartial->id,
        'product_id' => $orPartialItemA->product_id,
        'quantity' => 4,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Eklusif',
        'refer_item_model_id' => $orPartialItemA->id,
        'refer_item_model_type' => OrderRequestItem::class,
        'currency_id' => $currencyId,
    ]);

    $service->approvePo($poPartial, $userId);

    // Transition fixture 2: approved -> complete after approvePo
    $orComplete = OrderRequest::create([
        'request_number' => 'OR-TEST-A4-COMPLETE',
        'warehouse_id' => $warehouseId,
        'cabang_id' => $cabangId,
        'request_date' => now()->toDateString(),
        'status' => 'approved',
        'created_by' => $userId,
    ]);

    $orCompleteItemA = OrderRequestItem::create([
        'order_request_id' => $orComplete->id,
        'product_id' => $productA,
        'supplier_id' => $supplierId,
        'quantity' => 4,
        'fulfilled_quantity' => 0,
        'unit_price' => 100000,
        'original_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'subtotal' => 400000,
    ]);

    $orCompleteItemB = OrderRequestItem::create([
        'order_request_id' => $orComplete->id,
        'product_id' => $productB,
        'supplier_id' => $supplierId,
        'quantity' => 6,
        'fulfilled_quantity' => 0,
        'unit_price' => 120000,
        'original_price' => 120000,
        'discount' => 0,
        'tax' => 0,
        'subtotal' => 720000,
    ]);

    $poComplete = PurchaseOrder::create([
        'supplier_id' => $supplierId,
        'po_number' => 'PO-TEST-A4-COMPLETE',
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'warehouse_id' => $warehouseId,
        'tempo_hutang' => 30,
        'created_by' => $userId,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orComplete->id,
        'cabang_id' => $cabangId,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $poComplete->id,
        'product_id' => $orCompleteItemA->product_id,
        'quantity' => 4,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Eklusif',
        'refer_item_model_id' => $orCompleteItemA->id,
        'refer_item_model_type' => OrderRequestItem::class,
        'currency_id' => $currencyId,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $poComplete->id,
        'product_id' => $orCompleteItemB->product_id,
        'quantity' => 6,
        'unit_price' => 120000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Eklusif',
        'refer_item_model_id' => $orCompleteItemB->id,
        'refer_item_model_type' => OrderRequestItem::class,
        'currency_id' => $currencyId,
    ]);

    $service->approvePo($poComplete, $userId);

    echo "✅ A4 OR fixture ready\n";
    echo "   request_approve: OR-TEST-A4-REQAPP\n";
    echo "   approved       : OR-TEST-A4-APPROVED\n";
    echo "   partial        : OR-TEST-A4-PARTIAL\n";
    echo "   complete       : OR-TEST-A4-COMPLETE\n";
    echo "   closed         : OR-TEST-A4-CLOSED\n";
    echo "   rejected       : OR-TEST-A4-REJECTED\n";
});
