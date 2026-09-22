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
    $resource = file_get_contents(dirname(__DIR__, 2) . '/app/Filament/Resources/PurchaseOrderResource.php');
    $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/filament/forms/purchase-order-item-navigator.blade.php');

    expect($trait)
        ->toContain('updateInlinePurchaseOrderItemField')
        ->toContain('applyInlinePurchaseOrderItem')
        ->toContain('applyPendingInlinePurchaseOrderItemDrafts')
        ->toContain('addInlinePurchaseOrderItem')
        ->toContain('removeInlinePurchaseOrderItem')
        ->toContain('use Livewire\\Attributes\\Renderless;')
        ->toContain("#[Renderless]\n    public function searchInlinePurchaseOrderProducts")
        ->toContain("#[Renderless]\n    public function searchInlinePurchaseOrderCurrencies")
        ->toContain('searchInlinePurchaseOrderProducts')
        ->toContain('searchInlinePurchaseOrderCurrencies')
        ->toContain('recalculatePurchaseOrderItemPreviewState')
        ->toContain('isOrderRequestBacked');

    expect($resource)
        ->toContain('resolvePurchaseOrderItemIdFromValidationAttribute')
        ->toContain("array_search('purchaseOrderItem'")
        ->toContain("record-(\\d+)");

    expect($view)
        ->toContain('data-dt-po-navigator')
        ->toContain('Purchase order item')
        ->toContain('updateInlineItem')
        ->toContain('applyInlineItem')
        ->toContain('Simpan Perubahan Item')
        ->toContain('Klik Simpan Perubahan Item agar masuk ke')
        ->toContain('total/summary.')
        ->toContain('Tidak ada perubahan item. Ubah')
        ->toContain('qty/harga/diskon/pajak jika ingin menyimpan perubahan item.')
        ->toContain('Belum ada perubahan item untuk disimpan.')
        ->toContain('Menyimpan perubahan item...')
        ->toContain('Belum diterapkan')
        ->toContain('addItem()')
        ->toContain('data-dt-po-inline-select')
        ->toContain('change.dtPoInline')
        ->toContain('dtPoSelect2Managed')
        ->toContain('syncInlineSelect2Values')
        ->toContain('syncInlineMoneyInputs')
        ->toContain('dropdownParent: jq(editor)')
        ->toContain('rootElement: null')
        ->toContain('rootEl()')
        ->toContain('window.__dtPoSelect2AssetsPromise')
        ->toContain('initInlineSelects')
        ->toContain('wire:key="dt-po-row-{{ $row[\'key\'] }}"')
        ->toContain('wire:key="dt-po-detail-{{ $row[\'key\'] }}"')
        ->toContain('wire:key="dt-po-empty-row"')
        ->toContain('toggleDetail(key)')
        ->toContain('expandedKey')
        ->toContain('data-dt-po-row="{{ $row[\'key\'] }}"')
        ->toContain('Source')
        ->toContain('Order Request')
        ->toContain('Manual')
        ->toContain('dt-po-table')
        ->toContain('dt-po-inline-editor')
        ->toContain('data-dt-po-inline-editor')
        ->toContain('data-dt-po-inline-select')
        ->toContain('dt-po-tax-select')
        ->toContain('appearance: none')
        ->toContain('-webkit-appearance: none')
        ->toContain('background-repeat: no-repeat')
        ->toContain('background-position: right 11px center')
        ->toContain("updateInlineItem(@js(\$row['key']), 'tipe_pajak', \$event.target.value)")
        ->toContain('data-dt-po-validation-summary')
        ->toContain('Ada item Purchase Order yang belum valid')
        ->toContain('validation_errors')
        ->toContain('initInlineSelects')
        ->toContain('ensureSelect2Assets')
        ->toContain('select2({')
        ->toContain(".on('change.dtPoInline'")
        ->toContain('searchInlinePurchaseOrderProducts')
        ->toContain('searchInlinePurchaseOrderCurrencies')
        ->toContain('dtPoSelect2Managed');
});
