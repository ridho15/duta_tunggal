<?php

namespace App\Console\Commands;

use App\Models\InventoryStock;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Services\PurchaseReceiptService;
use Illuminate\Console\Command;

class ReconcilePurchaseReceiptStock extends Command
{
    protected $signature = 'purchase-receipt:reconcile-stock
                            {--receipt-id= : Reconcile items for a specific purchase receipt}
                            {--item-id= : Reconcile a single purchase receipt item}
                            {--all : Reconcile all purchase receipt items}
                            {--dry-run : Show what would change without writing data}
                            {--yes : Execute without confirmation}';

    protected $description = 'Reconcile missing StockMovement and InventoryStock rows for purchase receipt items';

    public function handle(PurchaseReceiptService $service): int
    {
        $query = PurchaseReceiptItem::query()->with([
            'purchaseReceipt',
            'purchaseOrderItem',
            'product',
            'warehouse',
            'rak',
            'qualityControl',
        ]);

        if ($itemId = $this->option('item-id')) {
            $query->whereKey($itemId);
        } elseif ($receiptId = $this->option('receipt-id')) {
            $query->where('purchase_receipt_id', $receiptId);
        } elseif (! $this->option('all')) {
            $this->error('Specify --receipt-id, --item-id, or --all.');

            return self::FAILURE;
        }

        $items = $query->orderBy('id')->get();

        if ($items->isEmpty()) {
            $this->info('No purchase receipt items found for the given filter.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $repaired = 0;
        $alreadyGood = 0;

        foreach ($items as $item) {
            $qc = $this->resolveCompletedQc($item);
            $movementSource = $qc ? [QualityControl::class, $qc->id] : [PurchaseReceiptItem::class, $item->id];
            $movementExists = $this->movementExistsForAnySource($item, $qc);
            $stockRow = $this->inventoryStockRow($item);
            $computedQty = $this->computedQtyFromMovements($item);
            $qtyAccepted = (float) ($item->qty_accepted ?? 0);
            $needsFix = ! $movementExists || ! $stockRow || abs((float) ($stockRow->qty_available ?? 0) - $computedQty) >= 0.00001;

            $rows[] = [
                $item->id,
                $item->purchaseReceipt?->receipt_number ?? '-',
                $item->product?->name ?? '-',
                $item->warehouse?->name ?? '-',
                $qc?->qc_number ?? '-',
                $movementExists ? 'yes' : 'no',
                $stockRow ? (string) (float) $stockRow->qty_available : 'missing',
                (string) $computedQty,
                $needsFix ? 'fix' : 'ok',
            ];

            if (! $needsFix) {
                $alreadyGood++;
                continue;
            }

            if ($dryRun) {
                continue;
            }

            $service->reconcileReceiptItemStock($item);
            $repaired++;
        }

        $this->table(
            ['Item ID', 'Receipt', 'Product', 'Warehouse', 'QC', 'Movement', 'Stock Qty', 'Calc Qty', 'Action'],
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

    protected function resolveCompletedQc(PurchaseReceiptItem $item): ?QualityControl
    {
        return QualityControl::where('from_model_type', \App\Models\PurchaseOrderItem::class)
            ->where('from_model_id', $item->purchase_order_item_id)
            ->where('status', 1)
            ->orderByDesc('id')
            ->first();
    }

    protected function movementExistsForAnySource(PurchaseReceiptItem $item, ?QualityControl $qc): bool
    {
        if ($qc) {
            $qcMovement = StockMovement::where('from_model_type', QualityControl::class)
                ->where('from_model_id', $qc->id)
                ->exists();

            if ($qcMovement) {
                return true;
            }
        }

        return StockMovement::where('from_model_type', PurchaseReceiptItem::class)
            ->where('from_model_id', $item->id)
            ->exists();
    }

    protected function inventoryStockRow(PurchaseReceiptItem $item): ?InventoryStock
    {
        return InventoryStock::where('product_id', $item->product_id)
            ->where('warehouse_id', $item->warehouse_id)
            ->when($item->rak_id !== null, fn ($query) => $query->where('rak_id', $item->rak_id), fn ($query) => $query->whereNull('rak_id'))
            ->first();
    }

    protected function computedQtyFromMovements(PurchaseReceiptItem $item): float
    {
        $query = StockMovement::query()
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $item->warehouse_id)
            ->when($item->rak_id !== null, fn ($builder) => $builder->where('rak_id', $item->rak_id), fn ($builder) => $builder->whereNull('rak_id'));

        $inTypes = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
        $outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

        $qtyIn = (float) (clone $query)->whereIn('type', $inTypes)->sum('quantity');
        $qtyOut = (float) (clone $query)->whereIn('type', $outTypes)->sum('quantity');

        return $qtyIn - $qtyOut;
    }
}