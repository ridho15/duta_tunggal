<?php

namespace App\Observers;

use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use Illuminate\Support\Facades\DB;

class MaterialIssueItemObserver
{
    /**
     * Handle the MaterialIssueItem "updated" event.
     */
    public function updated(MaterialIssueItem $item): void
    {
        // Check if this item was just completed
        $originalStatus = $item->getOriginal('status');
        if ($originalStatus !== MaterialIssueItem::STATUS_COMPLETED && $item->isCompleted()) {
            $this->checkAndCompleteMaterialIssue($item->material_issue_id);
        }

        // Check if this item was just set to pending approval
        if ($originalStatus !== MaterialIssueItem::STATUS_PENDING_APPROVAL && $item->isPendingApproval()) {
            $this->checkAndSetMaterialIssuePendingApproval($item->material_issue_id);
        }
    }

    /**
     * Check if all items in the material issue are completed, and if so, complete the material issue
     */
    protected function checkAndCompleteMaterialIssue(int $materialIssueId): void
    {
        // Check if all items are completed
        $allItemsCompleted = MaterialIssueItem::where('material_issue_id', $materialIssueId)
            ->where('status', '!=', MaterialIssueItem::STATUS_COMPLETED)
            ->doesntExist();

        if ($allItemsCompleted) {
            // All items are completed, so complete the material issue
            $materialIssue = MaterialIssue::find($materialIssueId);
            if ($materialIssue && !$materialIssue->isCompleted()) {
                $materialIssue->update(['status' => MaterialIssue::STATUS_COMPLETED]);
            }
        }
    }

    /**
     * Check if all items in the material issue are pending approval, and if so, set material issue to pending approval
     */
    protected function checkAndSetMaterialIssuePendingApproval(int $materialIssueId): void
    {
        // Check if all items are pending approval or higher status
        $allItemsPendingOrHigher = MaterialIssueItem::where('material_issue_id', $materialIssueId)
            ->whereIn('status', [MaterialIssueItem::STATUS_DRAFT])
            ->doesntExist();

        if ($allItemsPendingOrHigher) {
            // All items are at least pending approval, so set material issue to pending approval
            $materialIssue = MaterialIssue::find($materialIssueId);
            if ($materialIssue && $materialIssue->isDraft()) {
                $materialIssue->update(['status' => MaterialIssue::STATUS_PENDING_APPROVAL]);
            }
        }
    }
}
