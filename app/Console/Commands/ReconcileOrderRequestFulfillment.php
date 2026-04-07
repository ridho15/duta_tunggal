<?php

namespace App\Console\Commands;

use App\Models\OrderRequestItem;
use App\Models\PurchaseReceiptItem;
use Illuminate\Console\Command;

class ReconcileOrderRequestFulfillment extends Command
{
    protected $signature = 'order-request:reconcile-fulfillment
                            {--order-request-id= : Reconcile items for a specific order request}
                            {--item-id= : Reconcile a single order request item}
                            {--all : Reconcile all order request items}
                            {--dry-run : Show what would change without writing data}
                            {--yes : Execute without confirmation}';

    protected $description = 'Recalculate OrderRequestItem fulfilled_quantity from accepted purchase receipt items';

    public function handle(): int
    {
        $query = OrderRequestItem::query()->with(['purchaseOrderItem.referItemModel', 'orderRequest']);

        if ($itemId = $this->option('item-id')) {
            $query->whereKey($itemId);
        } elseif ($orderRequestId = $this->option('order-request-id')) {
            $query->where('order_request_id', $orderRequestId);
        } elseif (! $this->option('all')) {
            $this->error('Specify --order-request-id, --item-id, or --all.');

            return self::FAILURE;
        }

        $items = $query->orderBy('id')->get();

        if ($items->isEmpty()) {
            $this->info('No order request items found for the given filter.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $repaired = 0;
        $alreadyGood = 0;

        foreach ($items as $item) {
            $purchaseOrderItem = $item->purchaseOrderItem;
            $computedFulfilled = $purchaseOrderItem
                ? (float) PurchaseReceiptItem::query()
                    ->where('purchase_order_item_id', $purchaseOrderItem->id)
                    ->sum('qty_accepted')
                : 0.0;

            $currentFulfilled = (float) ($item->fulfilled_quantity ?? 0);
            $needsFix = abs($currentFulfilled - $computedFulfilled) >= 0.00001;

            $rows[] = [
                $item->id,
                $item->order_request_id,
                $item->product?->name ?? '-',
                (string) $currentFulfilled,
                (string) $computedFulfilled,
                $needsFix ? 'fix' : 'ok',
            ];

            if (! $needsFix) {
                $alreadyGood++;
                continue;
            }

            if ($dryRun) {
                continue;
            }

            $item->update(['fulfilled_quantity' => $computedFulfilled]);
            $item->orderRequest?->syncFulfillmentStatus();
            $repaired++;
        }

        $this->table(
            ['Item ID', 'Order Request', 'Product', 'Current Fulfilled', 'Computed Fulfilled', 'Action'],
            $rows
        );

        $this->info('Items inspected: ' . $items->count());
        $this->info('Already consistent: ' . $alreadyGood);
        $this->info($dryRun ? 'Dry-run only, no changes written.' : 'Repaired items: ' . $repaired);

        if (! $dryRun && ! $this->option('yes')) {
            $this->warn('Reconciliation completed without --yes; data was still written because this command is non-interactive by design.');
        }

        return self::SUCCESS;
    }
}