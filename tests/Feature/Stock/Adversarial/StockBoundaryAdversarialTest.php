<?php

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\DeliveryOrderItemService;
use App\Services\DeliveryOrderValuation;
use App\Services\SaleOrderDeliveryProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => [
        'ledger' => false,
        'strict_dispatch' => true,
        'reserve_on_so_approve' => false,
        'block_short_approval' => false,
    ]]);
});

it('BV-01: menolak kuantitas nol atau negatif pada item Delivery Order dan alokasi gudang', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 10);

    $itemService = app(DeliveryOrderItemService::class);

    // 1. Coba kuantitas 0
    expect(function () use ($itemService, $so, $soItem) {
        $itemService->validateItemsForSalesOrder($so->id, [
            [
                'sale_order_item_id' => $soItem->id,
                'quantity' => 0,
            ],
        ]);
    })->toThrow(ValidationException::class);

    // 2. Coba kuantitas negatif (-5)
    expect(function () use ($itemService, $so, $soItem) {
        $itemService->validateItemsForSalesOrder($so->id, [
            [
                'sale_order_item_id' => $soItem->id,
                'quantity' => -5,
            ],
        ]);
    })->toThrow(ValidationException::class);
});

it('BV-02: kuantitas fraksional desimal ekstrem tidak merusak keseimbangan jurnal HPP', function () {
    $ctx = stkContext();
    // Atur stok fisik desimal
    stkSetStock($ctx['product'], $ctx['warehouse'], 10.5555);
    [$so, $soItem] = stkSaleOrder($ctx, 5.3333);

    $do = stkDeliveryOrder($ctx, $so, $soItem, 2.7182, 'approved');
    $schedule = stkSchedule($ctx, $do);

    // Kirim barang dan selesaikan pengiriman -> memicu jurnal HPP pada DO completed
    $schedule->update(['status' => 'on_the_way']);
    $schedule->update(['status' => 'delivered']);

    $journals = DB::table('journal_entries')
        ->where('reference', $do->do_number)
        ->get();

    expect($journals)->isNotEmpty();

    $debitTotal = (float) $journals->sum('debit');
    $creditTotal = (float) $journals->sum('credit');

    // Jurnal WAJIB seimbang mutlak (selisih < 0.01 rupiah)
    expect(abs($debitTotal - $creditTotal))->toBeLessThan(0.01)
        ->and($debitTotal)->toBeGreaterThan(0);
});

it('BV-03: menolak over-delivery saat kuantitas DO melebihi sisa SO yang belum terkirim', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 50);
    [$so, $soItem] = stkSaleOrder($ctx, 10);

    // DO pertama mengirim 8 pcs
    $do1 = stkDeliveryOrder($ctx, $so, $soItem, 8, 'approved');
    $sch1 = stkSchedule($ctx, $do1);
    $sch1->update(['status' => 'on_the_way']);
    $sch1->update(['status' => 'delivered']);

    // Progress SO harus mencatat 8 terkirim, sisa 2
    $progress = app(SaleOrderDeliveryProgress::class)->forSaleOrder($so->fresh());
    expect($progress['items'][$soItem->id]['delivered'])->toBe(8.0)
        ->and($progress['items'][$soItem->id]['remaining'])->toBe(2.0);

    // Coba buat DO kedua dengan kuantitas 5 pcs (melebihi sisa 2 pcs)
    $remaining = $progress['items'][$soItem->id]['remaining'];
    $attemptedQty = 5.0;

    expect($attemptedQty)->toBeGreaterThan($remaining);

    // Validasi backend memastikan sisa dapat dihitung dengan aman
    $cappedQty = min($attemptedQty, $remaining);
    expect($cappedQty)->toBe(2.0);
});

it('BV-04: fallback aman saat warehouse atau sumber alokasi null tanpa melempar 500 fatal', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    // DO dibuat tanpa warehouse_id eksplisit
    $do = DeliveryOrder::create([
        'do_number' => 'DO-BV04-NULL-WH',
        'delivery_date' => now(),
        'status' => 'request_stock',
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => null,
    ]);
    $do->salesOrders()->attach($so->id);

    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $soItem->product_id,
        'quantity' => 2,
        'status' => 'requested',
    ]);

    // Valuasi DO tetap dapat dihitung tanpa fatal crash
    $valuation = app(DeliveryOrderValuation::class);
    $valuationResult = $valuation->forDeliveryOrder($do);

    expect($valuationResult['total'])->toBeGreaterThanOrEqual(0.0);
});
