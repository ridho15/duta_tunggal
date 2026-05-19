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
use Illuminate\Support\Facades\Schema;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;
use App\Services\TaxService;

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
        $row = [
            'request_number' => $requestNumber,
            'request_date' => now()->toDateString(),
            'status' => $status,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('order_requests', 'warehouse_id')) {
            $row['warehouse_id'] = $warehouseId;
        }
        if (Schema::hasColumn('order_requests', 'cabang_id')) {
            $row['cabang_id'] = $cabangId;
        }
        return DB::table('order_requests')->insertGetId($row);
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
    $fixtureSuffix = $now->format('YmdHisv') . '-' . bin2hex(random_bytes(3));

    // Transition fixture 1: approved -> partial after approvePo
    $orPartialData = [
        'request_number' => 'OR-TEST-A4-PARTIAL',
        'request_date' => now()->toDateString(),
        'status' => 'approved',
        'created_by' => $userId,
    ];
    if (Schema::hasColumn('order_requests', 'warehouse_id')) {
        $orPartialData['warehouse_id'] = $warehouseId;
    }
    if (Schema::hasColumn('order_requests', 'cabang_id')) {
        $orPartialData['cabang_id'] = $cabangId;
    }
    $orPartial = OrderRequest::create($orPartialData);

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

    $poPartialData = [
        'supplier_id' => $supplierId,
        'po_number' => 'PO-TEST-A4-PARTIAL-' . $fixtureSuffix,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'tempo_hutang' => 30,
        'created_by' => $userId,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orPartial->id,
    ];
    if (Schema::hasColumn('purchase_orders', 'warehouse_id')) {
        $poPartialData['warehouse_id'] = $warehouseId;
    }
    if (Schema::hasColumn('purchase_orders', 'cabang_id')) {
        $poPartialData['cabang_id'] = $cabangId;
    }
    $poPartial = PurchaseOrder::create($poPartialData);

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
    $orCompleteData = [
        'request_number' => 'OR-TEST-A4-COMPLETE',
        'request_date' => now()->toDateString(),
        'status' => 'approved',
        'created_by' => $userId,
    ];
    if (Schema::hasColumn('order_requests', 'warehouse_id')) {
        $orCompleteData['warehouse_id'] = $warehouseId;
    }
    if (Schema::hasColumn('order_requests', 'cabang_id')) {
        $orCompleteData['cabang_id'] = $cabangId;
    }
    $orComplete = OrderRequest::create($orCompleteData);

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

    $poCompleteData = [
        'supplier_id' => $supplierId,
        'po_number' => 'PO-TEST-A4-COMPLETE-' . $fixtureSuffix,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'tempo_hutang' => 30,
        'created_by' => $userId,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orComplete->id,
    ];
    if (Schema::hasColumn('purchase_orders', 'warehouse_id')) {
        $poCompleteData['warehouse_id'] = $warehouseId;
    }
    if (Schema::hasColumn('purchase_orders', 'cabang_id')) {
        $poCompleteData['cabang_id'] = $cabangId;
    }
    $poComplete = PurchaseOrder::create($poCompleteData);

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

    $orTaxId = $createOr('OR-TEST-A4-TAX', 'approved');

    DB::table('order_requests')
        ->where('id', $orTaxId)
        ->update([
            'tax_type' => 'PPN Excluded',
            'updated_at' => $now,
        ]);

    $taxBase = 3 * 100000;
    $taxResult = TaxService::compute($taxBase, 11, 'PPN Excluded');

    DB::table('order_request_items')->insert([
        'order_request_id' => $orTaxId,
        'product_id' => $productA,
        'supplier_id' => $supplierId,
        'quantity' => 3,
        'fulfilled_quantity' => 1,
        'unit_price' => 100000,
        'original_price' => 100000,
        'discount' => 0,
        'tax' => 11,
        'subtotal' => $taxResult['total'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "✅ A4 OR fixture ready\n";
    echo "   request_approve: OR-TEST-A4-REQAPP\n";
    echo "   approved       : OR-TEST-A4-APPROVED\n";
    echo "   partial        : OR-TEST-A4-PARTIAL\n";
    echo "   complete       : OR-TEST-A4-COMPLETE\n";
    echo "   tax           : OR-TEST-A4-TAX\n";
    echo "   closed         : OR-TEST-A4-CLOSED\n";
    echo "   rejected       : OR-TEST-A4-REJECTED\n";
});
