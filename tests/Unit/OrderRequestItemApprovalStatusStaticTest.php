<?php

function project_file(string $path): string
{
    return file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
}
it('adds item approval columns and model helpers for order request items', function () {
    $migration = project_file('database/migrations/2026_06_24_140000_add_approval_status_to_order_request_items_table.php');
    $model = project_file('app/Models/OrderRequestItem.php');
    $orderRequest = project_file('app/Models/OrderRequest.php');

    expect($migration)
        ->toContain("'status'")
        ->toContain("'approved_by'")
        ->toContain("'approved_at'")
        ->toContain("'rejected_by'")
        ->toContain("'rejected_at'")
        ->toContain("'rejection_note'");

    expect($model)
        ->toContain("public const STATUS_DRAFT = 'draft'")
        ->toContain("public const STATUS_APPROVED = 'approved'")
        ->toContain("public const STATUS_REJECTED = 'rejected'")
        ->toContain('function approvedBy()')
        ->toContain('function rejectedBy()')
        ->toContain('normalizeApprovalStatus');

    expect($orderRequest)
        ->toContain('function syncItemApprovalStatus')
        ->toContain("['status' => 'partial']")
        ->toContain("['status' => 'approved']")
        ->toContain("['status' => 'rejected']");
});

it('wires item approval decisions into order request approval service', function () {
    $service = project_file('app/Services/OrderRequestService.php');

    expect($service)
        ->toContain('applyItemApprovalDecisions')
        ->toContain('ensureAllItemsHaveApprovalDecision')
        ->toContain('Keputusan item wajib diisi sebelum Order Request dapat di-approve.')
        ->toContain('Masih ada item berstatus Draft. Ambil keputusan Approve atau Reject untuk semua item sebelum menyetujui Order Request.')
        ->toContain('Alasan reject wajib diisi untuk item yang ditolak.')
        ->toContain('OrderRequestItem::STATUS_APPROVED')
        ->toContain('OrderRequestItem::STATUS_REJECTED')
        ->toContain('OrderRequestItem::STATUS_DRAFT')
        ->toContain('syncItemApprovalStatus')
        ->toContain("['status' => 'request_approve']");
});

it('uses real checkboxes and bulk status actions in the custom order request item navigator', function () {
    $blade = project_file('resources/views/filament/forms/order-request-item-navigator.blade.php');
    $trait = project_file('app/Filament/Resources/OrderRequestResource/Pages/Concerns/InteractsWithInlineOrderRequestItems.php');

    $toolbarLeftStart = strpos($blade, 'class="dt-item-toolbar-left"');
    $toolbarLeftEnd = strpos($blade, 'class="dt-item-nav-actions"', $toolbarLeftStart);
    $toolbarLeftMarkup = substr($blade, $toolbarLeftStart, $toolbarLeftEnd - $toolbarLeftStart);

    expect($blade)
        ->toContain('dt-item-toolbar-main')
        ->toContain('dt-item-bulk-actions')
        ->toContain('data-dt-or-bulk-row')
        ->toContain('data-dt-or-collapse-all')
        ->toContain('data-dt-or-add-navigator')
        ->toContain('type="checkbox"')
        ->toContain('selectedKeys')
        ->toContain('bulkUpdateInlineOrderRequestItemStatus')
        ->toContain('bulkUpdateInlineOrderRequestItemSupplier')
        ->toContain('bulkUpdateInlineOrderRequestItemCabang')
        ->toContain('updateInlineOrderRequestItemStatus')
        ->toContain('data-dt-or-bulk-supplier-select')
        ->toContain('data-dt-or-bulk-cabang-select')
        ->toContain('data-dt-or-bulk-set-supplier')
        ->toContain('data-dt-or-bulk-set-cabang')
        ->toContain('Set Supplier')
        ->toContain('Set Cabang')
        ->toContain('Approve Selected')
        ->toContain('Reject Selected')
        ->toContain('Set Draft')
        ->toContain('clearSelection()')
        ->toContain('data-dt-or-approve-item')
        ->toContain('data-dt-or-reject-item')
        ->toContain('data-dt-or-draft-item')
        ->toContain('setItemStatus(@js($row[\'key\']), \'approved\')')
        ->toContain('dt-item-status-badge')
        ->toContain('wire:key="dt-or-row-{{ $row[\'key\'] }}"')
        ->toContain('wire:key="dt-or-detail-{{ $row[\'key\'] }}"')
        ->toContain('wire:key="dt-or-empty-row"')
        ->toContain('openItemWhenReady')
        ->toContain('this.openItemWhenReady(newKey)')
        ->toContain('window.setTimeout(() => this.openItemWhenReady(normalizedKey, attempts - 1), 120)')
        ->toContain('if (this.activeKey === String(key))')
        ->toContain('this.scrollEditorIntoView(normalizedKey, true)')
        ->not->toContain('span class="dt-item-checkbox"')
        ->not->toContain('disabled data-dt-or-bulk-actions');

    expect($toolbarLeftMarkup)
        ->not->toContain('data-dt-or-bulk-actions')
        ->not->toContain('Reject Selected')
        ->not->toContain('Clear Selection');

    expect($trait)
        ->toContain('bulkUpdateInlineOrderRequestItemStatus')
        ->toContain('public function bulkUpdateInlineOrderRequestItemSupplier')
        ->toContain('public function bulkUpdateInlineOrderRequestItemCabang')
        ->toContain('public function updateInlineOrderRequestItemStatus')
        ->toContain('applyInlineOrderRequestItemFieldUpdate($item, \'supplier_id\', $supplierId)')
        ->toContain('inlineOrderRequestCabangIsAllowed')
        ->toContain('Alasan reject wajib diisi')
        ->toContain('Item tidak dapat diedit')
        ->toContain('OrderRequestItem::STATUS_DRAFT');
});

it('shows item approval summaries on index and relation manager', function () {
    $resource = project_file('app/Filament/Resources/OrderRequestResource.php');
    $relationManager = project_file('app/Filament/Resources/OrderRequestResource/RelationManagers/OrderRequestItemsRelationManager.php');

    expect($resource)
        ->toContain('use App\\Models\\OrderRequestItem;')
        ->toContain('renderIndexItemSummary')
        ->toContain('approved</span>')
        ->toContain('rejected</span>')
        ->toContain('draft</span>')
        ->toContain("'status_value' =>")
        ->toContain("'is_status_locked' =>");

    expect($relationManager)
        ->toContain("use App\Models\OrderRequestItem;")
        ->toContain("->label('Status Item')")
        ->toContain("SelectFilter::make('status')")
        ->toContain("OrderRequestItem::STATUS_APPROVED => 'Approved'")
        ->toContain("OrderRequestItem::STATUS_REJECTED => 'Rejected'");
});

it('requires explicit item decisions when approving an order request', function () {
    $resource = project_file('app/Filament/Resources/OrderRequestResource.php');
    $viewPage = project_file('app/Filament/Resources/OrderRequestResource/Pages/ViewOrderRequest.php');

    expect($resource)
        ->toContain('buildPurchaseOrderSelectedItemsRepeater(bool $includeDependsOnAutoPurchaseOrder = false)')
        ->toContain("->label('Keputusan Item')")
        ->toContain('OrderRequestItem::STATUS_DRAFT => \'Tetap Draft\',')
        ->toContain('self::validateApprovalGateItemDecisions($data)')
        ->toContain('Keputusan item wajib dipilih.')
        ->toContain('OrderRequestItem::STATUS_APPROVED => \'Approve\',')
        ->toContain('OrderRequestItem::STATUS_REJECTED => \'Reject\',')
        ->toContain('selectedPurchaseOrderApprovedItems')
        ->toContain('approvalOutcomeNotification')
        ->not->toContain('$record->update([\'status\' => \'approved\']);');

    expect($viewPage)
        ->toContain('OrderRequestResource::buildPurchaseOrderSelectedItemsRepeater(includeDependsOnAutoPurchaseOrder: true)')
        ->toContain('OrderRequestResource::selectedPurchaseOrderApprovedItems($data[\'selected_items\'] ?? [])')
        ->toContain('Purchase Order tidak akan dibuat otomatis.')
        ->toContain('Order Request disetujui, tetapi tidak ada Purchase Order dibuat karena tidak ada item Approved yang dicentang untuk dibuatkan PO otomatis.')
        ->not->toContain('$record->update([\'status\' => \'approved\']);');
});

it('keeps the custom order request product select stable with native fallback options', function () {
    $resource = project_file('app/Filament/Resources/OrderRequestResource.php');
    $trait = project_file('app/Filament/Resources/OrderRequestResource/Pages/Concerns/InteractsWithInlineOrderRequestItems.php');
    $blade = project_file('resources/views/filament/forms/order-request-item-navigator.blade.php');

    expect($resource)
        ->toContain("\$query = Product::withoutGlobalScope('product_cabang')->orderBy('name');")
        ->toContain(': self::resolveProductOptions(limit: 50)');

    expect($trait)
        ->toContain("Product::withoutGlobalScope('product_cabang')->with('uom')->find(\$normalizedValue)")
        ->toContain("public function searchInlineOrderRequestProducts(string \$search = ''): array\n    {\n        \$this->skipRender();")
        ->toContain("public function searchInlineOrderRequestSuppliers(\n        ?int \$productId = null,\n        ?int \$currencyId = null,\n        string \$search = ''\n    ): array {\n        \$this->skipRender();");

    expect($blade)
        ->toContain('dropdownParent: jq(editor)')
        ->toContain('dt-or-inline-select2-dropdown')
        ->toContain('.dt-item-detail-card');
});

it('refreshes inline select2 controls after livewire refreshes', function () {
    $blade = project_file('resources/views/filament/forms/order-request-item-navigator.blade.php');

    expect($blade)
        ->toContain('refreshInlineSelects')
        ->toContain('rootElement: null')
        ->toContain('rootEl()')
        ->toContain('syncInlineSelect2Values')
        ->toContain('isSelect2Managed')
        ->toContain("trigger('change.select2')")
        ->toContain('syncInlineMoneyInputs')
        ->toContain("querySelectorAll('input[data-dt-inline-unit-price]')")
        ->toContain("input.value = renderedValue")
        ->toContain('registerInlineSelectRefreshHooks')
        ->toContain("\$select.data('select2')")
        ->toContain("\$select.select2('destroy')")
        ->toContain("window.__dtOrSelect2LivewireHooksRegistered")
        ->toContain("window.Livewire.hook('message.processed'")
        ->toContain("window.Livewire.hook('morph.updated'")
        ->toContain('alpine.refreshInlineSelects?.call(alpine, 120)')
        ->toContain('alpine.syncInlineEditorLockState?.call(alpine)')
        ->toContain('change.dtOrInline')
        ->toContain('this.handleInlineSelectChange(select)')
        ->toContain('forcedValue ?? select.value')
        ->toContain('data-dt-select2-updating')
        ->toContain('data-dt-select2-open')
        ->toContain('change.dtOrInline')
        ->toContain("editor?.querySelector('select[data-dt-select2-open]')")
        ->toContain('currentInlineSelectValue(editor, field)')
        ->toContain('latestProductId')
        ->toContain('latestCurrencyId')
        ->toContain('searchInlineOrderRequestSuppliers(latestProductId, latestCurrencyId, search)')
        ->toContain("dropdownCssClass: 'dt-or-inline-select2-dropdown'")
        ->not->toContain('this.$root.querySelector')
        ->not->toContain('searchInlineOrderRequestSuppliers(productId, currencyId, search)');
});

it('only allows approved order request items to feed purchase orders', function () {
    $purchaseOrderResource = project_file('app/Filament/Resources/PurchaseOrderResource.php');
    $viewPage = project_file('app/Filament/Resources/OrderRequestResource/Pages/ViewOrderRequest.php');

    expect($purchaseOrderResource)
        ->toContain('public static function isOrderRequestItemEligibleForPurchaseOrder(OrderRequestItem $orderRequestItem): bool')
        ->toContain('OrderRequestItem::normalizeApprovalStatus($orderRequestItem->status ?? null) === OrderRequestItem::STATUS_APPROVED')
        ->toContain('if (! static::isOrderRequestItemEligibleForPurchaseOrder($orderRequestItem))')
        ->toContain('getAvailableOrderRequestItemGroups(OrderRequest $orderRequest): array')
        ->toContain('buildOrderRequestItems(')
        ->toContain('getAvailableOrderRequestSupplierIds(OrderRequest $orderRequest): array');

    expect($viewPage)
        ->toContain('use App\Filament\Resources\PurchaseOrderResource;')
        ->toContain('PurchaseOrderResource::isOrderRequestItemEligibleForPurchaseOrder($item)')
        ->toContain("'approval_status'  => OrderRequestItem::normalizeApprovalStatus(\$item->status ?? null)")
        ->toContain('OrderRequestResource::selectedPurchaseOrderApprovedItems($data[\'selected_items\'] ?? [])')
        ->toContain('hasApprovedItemsAvailableForPurchaseOrder($record)')
        ->not->toContain('// Show button as long as some items still have unfulfilled quantity');
});
