<?php

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Filament\Resources\OrderRequestResource;
use Database\Seeders\LargePurchaseFlowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('large purchase flow demo seeder creates one OR with two large PO records', function () {
    $this->seed(LargePurchaseFlowDemoSeeder::class);

    $orderRequest = OrderRequest::where('request_number', 'OR-LARGE-DEMO-001')->first();

    expect($orderRequest)->not->toBeNull();
    expect($orderRequest->orderRequestItem()->count())->toBe(120);

    $purchaseOrders = PurchaseOrder::whereIn('po_number', [
        'PO-LARGE-DEMO-A',
        'PO-LARGE-DEMO-B',
    ])->with('purchaseOrderItem')->get()->keyBy('po_number');

    expect($purchaseOrders)->toHaveCount(2);
    expect($purchaseOrders['PO-LARGE-DEMO-A']->refer_model_type)->toBe(OrderRequest::class);
    expect((int) $purchaseOrders['PO-LARGE-DEMO-A']->refer_model_id)->toBe((int) $orderRequest->id);
    expect($purchaseOrders['PO-LARGE-DEMO-A']->purchaseOrderItem)->toHaveCount(60);
    expect($purchaseOrders['PO-LARGE-DEMO-B']->purchaseOrderItem)->toHaveCount(60);

    $purchaseOrders->flatMap->purchaseOrderItem->each(function ($purchaseOrderItem) {
        expect($purchaseOrderItem->refer_item_model_type)->toBe(OrderRequestItem::class);
        expect($purchaseOrderItem->refer_item_model_id)->not->toBeNull();

        $sourceItem = OrderRequestItem::find($purchaseOrderItem->refer_item_model_id);

        expect($sourceItem)->not->toBeNull();
        expect($purchaseOrderItem->product_id)->toBe($sourceItem->product_id);
        expect($purchaseOrderItem->tipe_pajak)->toBe($sourceItem->tipe_pajak);
    });
});

test('large purchase item UX keeps repeaters safe and relation manager review paginated', function () {
    $orderRequestResource = file_get_contents(app_path('Filament/Resources/OrderRequestResource.php'));
    $orderRequestNavigator = file_get_contents(resource_path('views/filament/forms/order-request-item-navigator.blade.php'));
    $purchaseOrderResource = file_get_contents(app_path('Filament/Resources/PurchaseOrderResource.php'));
    $relationManager = file_get_contents(app_path('Filament/Resources/PurchaseOrderResource/RelationManagers/PurchaseOrderItemRelationManager.php'));

    expect($orderRequestResource)->toContain("Repeater::make('orderRequestItem')");
    expect($orderRequestResource)->toContain("_order_request_item_search");
    expect($orderRequestResource)->toContain("_order_request_item_supplier_filter");
    expect($orderRequestResource)->toContain("_order_request_item_cabang_filter");
    expect($orderRequestResource)->toContain("_order_request_item_tax_filter");
    expect($orderRequestResource)->toContain("_order_request_item_page_size");
    expect($orderRequestResource)->toContain("_order_request_item_page");
    expect($orderRequestResource)->toContain('usesLargeOrderRequestItemEditor');
    expect($orderRequestResource)->toContain("'class' => 'dt-or-large-repeater'");
    expect($orderRequestResource)->toContain("view('filament.forms.order-request-item-navigator'");
    expect($orderRequestNavigator)->toContain('dt-item-table');
    expect($orderRequestNavigator)->toContain('wire:key="dt-or-navigator-');
    expect($orderRequestNavigator)->toContain('data-dt-or-key');
    expect($orderRequestNavigator)->toContain('recentlyAddedKey:');
    expect($orderRequestNavigator)->toContain('recentlyAddedMessage:');
    expect($orderRequestNavigator)->toContain('data-dt-add-feedback');
    expect($orderRequestNavigator)->toContain('Item baru ditambahkan di baris paling atas');
    expect($orderRequestNavigator)->toContain('Item baru ditampilkan di halaman pertama');
    expect($orderRequestNavigator)->not->toContain('Item baru ditambahkan dan dibuka di halaman terakhir');
    expect($orderRequestNavigator)->toContain('dt-item-new-row-highlight');
    expect($orderRequestNavigator)->toContain('Item baru dibuka');
    expect($orderRequestNavigator)->toContain('data-dt-or-filter-toggle');
    expect($orderRequestNavigator)->toContain('data-dt-or-collapse-all');
    expect($orderRequestNavigator)->toContain('data-dt-or-bulk-actions');
    expect($orderRequestNavigator)->toContain('data-dt-or-add-navigator');
    expect($orderRequestNavigator)->toContain('x-on:keydown.enter.prevent.stop="void 0"');
    expect($orderRequestNavigator)->toContain("this.\$wire.set('data._order_request_item_page', 1, false)");
    expect($orderRequestNavigator)->toContain('setNavigatorPage(page)');
    expect($orderRequestNavigator)->toContain('toggleDetail(key)');
    expect($orderRequestNavigator)->toContain('scrollEditorIntoView(key');
    expect($orderRequestNavigator)->not->toContain('moveEditorToMount');
    expect($orderRequestNavigator)->not->toContain('returnEditorToDock');
    expect($orderRequestNavigator)->not->toContain('bridgeMountedEditorEvents');
    expect($orderRequestNavigator)->not->toContain('data-dt-or-editor-mount');
    expect($orderRequestNavigator)->not->toContain('appendChild');
    expect($orderRequestNavigator)->toContain('dt-item-detail-row');
    expect($orderRequestNavigator)->toContain('Editor item #');
    expect($orderRequestNavigator)->toContain('data-dt-inline-editor');
    expect($orderRequestNavigator)->toContain('data-dt-inline-quantity');
    expect($orderRequestNavigator)->toContain('data-dt-inline-unit-price');
    expect($orderRequestNavigator)->toContain('data-dt-inline-tax-type');
    expect($orderRequestNavigator)->toContain('dt-item-inline-select');
    expect($orderRequestNavigator)->toContain('appearance:none');
    expect($orderRequestNavigator)->toContain('background-repeat:no-repeat');
    expect($orderRequestNavigator)->toContain('data-dt-delete-item');
    expect($orderRequestNavigator)->toContain('removeInlineItem(@js($row');
    expect($orderRequestNavigator)->toContain('updateInlineItem(@js($row');
    expect($orderRequestNavigator)->toContain('Total Subtotal (IDR)');
    expect($orderRequestNavigator)->toContain('.dt-or-large-repeater .fi-fo-repeater-item{display:none!important}');
    expect($orderRequestNavigator)->toContain('.dt-or-large-repeater{height:0!important');
    expect($orderRequestNavigator)->toContain('this.$wire.addInlineOrderRequestItem()');
    expect($orderRequestNavigator)->not->toContain('window.__dtOrAwaitNewItem');
    expect($orderRequestNavigator)->not->toContain('addButton.click()');
    expect($orderRequestNavigator)->toContain('[aria-invalid=true]');
    expect($orderRequestNavigator)->not->toContain('<script');
    expect($orderRequestResource)->toContain("->itemNumbers()");
    expect($orderRequestResource)->toContain("collapseAllAction");
    expect($orderRequestResource)->toContain("expandAllAction");
    expect($orderRequestResource)->toContain("Product: {\$productName} | Supplier: {\$supplierName} | Cabang: {\$cabangName} | Qty:");

    expect($purchaseOrderResource)->toContain("Repeater::make('purchaseOrderItem')");
    expect($purchaseOrderResource)->toContain("_purchase_order_item_search");
    expect($purchaseOrderResource)->toContain("_purchase_order_item_tax_filter");
    expect($purchaseOrderResource)->toContain("_purchase_order_item_source_filter");
    expect($purchaseOrderResource)->toContain("_purchase_order_item_cabang_filter");
    expect($purchaseOrderResource)->toContain("Active filters:");
    expect($purchaseOrderResource)->toContain("Masih ada");
    expect($purchaseOrderResource)->toContain("Product: {\$productName} | Source: {\$source} | Qty:");

    expect($relationManager)->toContain('->paginated([10, 25, 50, 100])');
    expect($relationManager)->toContain("SelectFilter::make('tipe_pajak')");
    expect($relationManager)->toContain("SelectFilter::make('cabang_id')");
    expect($relationManager)->toContain("SelectFilter::make('receipt_qc_status')");
});

test('large OR editor activates only for edit operations with at least 25 items', function () {
    expect(OrderRequestResource::usesLargeOrderRequestItemEditor(array_fill(0, 24, []), 'edit'))->toBeFalse()
        ->and(OrderRequestResource::usesLargeOrderRequestItemEditor(array_fill(0, 25, []), 'edit'))->toBeTrue()
        ->and(OrderRequestResource::usesLargeOrderRequestItemEditor(array_fill(0, 120, []), 'create'))->toBeFalse();
});

test('large OR navigator renders only the selected page without discarding source items', function () {
    $items = collect(range(1, 30))->mapWithKeys(fn (int $number) => [
        "item-{$number}" => [
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'tipe_pajak' => 'eklusif',
        ],
    ])->all();

    $firstPage = OrderRequestResource::renderLargeItemSummary($items, pageSize: 10, currentPage: 1)->render();
    $thirdPage = OrderRequestResource::renderLargeItemSummary($items, pageSize: 10, currentPage: 3)->render();

    expect(substr_count($firstPage, 'class="dt-item-row"'))->toBe(10)
        ->and($firstPage)->toContain('Showing 1 to 10')
        ->and($firstPage)->toContain('of 30 items')
        ->and($firstPage)->toContain('data-dt-or-row="item-1"')
        ->and($firstPage)->not->toContain('data-dt-or-row="item-11"')
        ->and(substr_count($thirdPage, 'class="dt-item-row"'))->toBe(10)
        ->and($thirdPage)->toContain('Showing 21 to 30')
        ->and($thirdPage)->toContain('data-dt-or-row="item-30"');
});

test('order request index uses limited summaries for large item records', function () {
    $resource = file_get_contents(app_path('Filament/Resources/OrderRequestResource.php'));

    expect($resource)->toContain('renderIndexSupplierSummary')
        ->and($resource)->toContain('renderIndexItemSummary')
        ->and($resource)->toContain("TextColumn::make('order_request_item_count')")
        ->and($resource)->toContain("TextColumn::make('order_request_item_sum_quantity')")
        ->and($resource)->toContain("TextColumn::make('supplier_summary')")
        ->and($resource)->toContain("TextColumn::make('item_summary')")
        ->and($resource)->toContain("SelectFilter::make('cabang_id')")
        ->and($resource)->toContain("->withCount('orderRequestItem')")
        ->and($resource)->toContain("->withSum('orderRequestItem', 'quantity')")
        ->and($resource)->toContain("&& (int) (\$record->order_request_item_count ?? 0) > 0;")
        ->and($resource)->toContain('+\' . number_format($remaining, 0, \',\', \'.\') . \' item lainnya')
        ->and($resource)->not->toContain("TextColumn::make('supplier')\n                    ->label('Supplier')\n                    ->getStateUsing(function (\$record) {\n                        return \$record->orderRequestItem;")
        ->and($resource)->not->toContain("TextColumn::make('product')\n                    ->label('Items')\n                    ->getStateUsing(function (\$record) {\n                        return \$record->orderRequestItem;")
        ->and($resource)->not->toContain("return \$record->orderRequestItem->contains(\n                                fn(\$item) => OrderRequestQuantityLock::orderRequestItemLimit");
});

test('order request detail uses paginated item table for large item records', function () {
    $resource = file_get_contents(app_path('Filament/Resources/OrderRequestResource.php'));
    $relationManager = file_get_contents(app_path('Filament/Resources/OrderRequestResource/RelationManagers/OrderRequestItemsRelationManager.php'));

    expect($resource)->toContain('OrderRequestItemsRelationManager::class')
        ->and($resource)->toContain('Detail item besar ditampilkan pada tabel paginated')
        ->and($resource)->toContain('order_request_items_table_note')
        ->and($resource)->toContain("RepeatableEntry::make('orderRequestItem')")
        ->and($resource)->toContain('->visible(false)');

    expect($relationManager)->toContain("protected static string \$relationship = 'orderRequestItem'")
        ->and($relationManager)->toContain("->paginated([10, 25, 50, 100])")
        ->and($relationManager)->toContain('->defaultPaginationPageOption(25)')
        ->and($relationManager)->toContain("TextColumn::make('product.sku')")
        ->and($relationManager)->toContain("TextColumn::make('product.name')")
        ->and($relationManager)->toContain("TextColumn::make('supplier_summary')")
        ->and($relationManager)->toContain("TextColumn::make('cabang_summary')")
        ->and($relationManager)->toContain("SelectFilter::make('supplier_id')")
        ->and($relationManager)->toContain("SelectFilter::make('cabang_id')")
        ->and($relationManager)->toContain("SelectFilter::make('tipe_pajak')")
        ->and($relationManager)->toContain("SelectFilter::make('fulfillment_status')");
});
