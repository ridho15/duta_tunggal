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
        ->toContain('Approve Selected')
        ->toContain('Reject Selected')
        ->toContain('Set Draft')
        ->toContain('Clear Selection')
        ->toContain('dt-item-status-badge')
        ->not->toContain('span class="dt-item-checkbox"')
        ->not->toContain('disabled data-dt-or-bulk-actions');

    expect($toolbarLeftMarkup)
        ->not->toContain('data-dt-or-bulk-actions')
        ->not->toContain('Reject Selected')
        ->not->toContain('Clear Selection');

    expect($trait)
        ->toContain('bulkUpdateInlineOrderRequestItemStatus')
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