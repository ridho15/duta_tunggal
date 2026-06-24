<?php

it('wires purchase order item forms to the custom navigator', function () {
    $resource = file_get_contents(dirname(__DIR__, 2) . '/app/Filament/Resources/PurchaseOrderResource.php');
    $createPage = file_get_contents(dirname(__DIR__, 2) . '/app/Filament/Resources/PurchaseOrderResource/Pages/CreatePurchaseOrder.php');
    $editPage = file_get_contents(dirname(__DIR__, 2) . '/app/Filament/Resources/PurchaseOrderResource/Pages/EditPurchaseOrder.php');

    expect($resource)
        ->toContain('renderPurchaseOrderItemNavigator(')
        ->toContain("view('filament.forms.purchase-order-item-navigator'")
        ->toContain("\$item['_navigator_key'] = (string) \$key;")
        ->toContain('dt-po-large-repeater')
        ->toContain('usesLargePurchaseOrderItemEditor')
        ->toContain("Repeater::make('purchaseOrderItem')");

    expect($createPage)
        ->toContain('InteractsWithInlinePurchaseOrderItems')
        ->toContain('use InteractsWithInlinePurchaseOrderItems;');

    expect($editPage)
        ->toContain('InteractsWithInlinePurchaseOrderItems')
        ->toContain('use InteractsWithInlinePurchaseOrderItems;');
});

it('provides purchase order inline item operations and navigator UI', function () {
    $trait = file_get_contents(dirname(__DIR__, 2) . '/app/Filament/Resources/PurchaseOrderResource/Pages/Concerns/InteractsWithInlinePurchaseOrderItems.php');
    $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/filament/forms/purchase-order-item-navigator.blade.php');

    expect($trait)
        ->toContain('updateInlinePurchaseOrderItemField')
        ->toContain('addInlinePurchaseOrderItem')
        ->toContain('removeInlinePurchaseOrderItem')
        ->toContain('searchInlinePurchaseOrderProducts')
        ->toContain('searchInlinePurchaseOrderCurrencies')
        ->toContain('skipRender()')
        ->toContain('recalculatePurchaseOrderItemPreviewState')
        ->toContain('isOrderRequestBacked');

    expect($view)
        ->toContain('data-dt-po-navigator')
        ->toContain('Purchase order item')
        ->toContain('updateInlineItem')
        ->toContain('addItem()')
        ->toContain('data-dt-po-inline-select')
        ->toContain('select2:select.dtPoInline')
        ->toContain('select2:clear.dtPoInline')
        ->toContain('select2:open.dtPoInline')
        ->toContain('select2:close.dtPoInline')
        ->toContain('syncInlineSelect2Values')
        ->toContain("trigger('change.select2')")
        ->toContain('syncInlineMoneyInputs')
        ->toContain('dropdownParent: jq(document.body)')
        ->toContain('window.__dtPoSelect2LivewireHooksRegistered')
        ->toContain('wire:key="dt-po-row-{{ $row[\'key\'] }}"')
        ->toContain('wire:key="dt-po-detail-{{ $row[\'key\'] }}"')
        ->toContain('wire:key="dt-po-empty-row"')
        ->toContain('openItemWhenReady')
        ->toContain('window.setTimeout(() => this.openItemWhenReady(key), 120)')
        ->toContain('data-dt-po-row="{{ $row[\'key\'] }}"')
        ->toContain('Source')
        ->toContain('Order Request')
        ->toContain('Manual')
        ->toContain('dt-po-table')
        ->toContain('dt-po-inline-editor');

    expect($view)->not->toContain('change.dtPoInline');
});
